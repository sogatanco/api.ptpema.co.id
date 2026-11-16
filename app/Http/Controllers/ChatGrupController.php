<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Grup chat per divisi, terenkripsi end-to-end.
 *
 * Satu grup = satu divisi (organization_id). Anggota dihitung dari struktur (pegawai aktif), bukan disimpan.
 * Isi pesan dienkripsi AES-GCM dengan kunci grup (per versi) yang dibuat di browser dan dibungkus untuk setiap anggota
 * memakai ECDH antar kunci publik; server hanya menyimpan ciphertext dan kunci grup terbungkus.
 */
class ChatGrupController extends Controller
{
    private const RIWAYAT = 100;
    private const BATAS_CIPHERTEXT = 11200;

    // ---- keanggotaan ------------------------------------------------------------------------

    private function orgSaya(int $userId): ?int
    {
        $org = DB::table('struktur_lengkap_oke')->where('user_id', $userId)->value('organization_id');

        return $org === null ? null : (int) $org;
    }

    /** anggota aktif sebuah grup (Divisi Umum tanpa Tenaga Pendukung, dst. menurut config) */
    private function anggota(int $org)
    {
        $dikecualikan = (array) config("chat.grup_tanpa_golongan.$org", []);

        return DB::table('struktur_lengkap_oke as s')
            ->join('employees_hr as e', 'e.user_id', '=', 's.user_id')
            ->where('e.employe_active', 1)->where('s.organization_id', $org)
            ->when($dikecualikan, fn ($q) => $q->whereNotIn('s.id_base', $dikecualikan))
            ->get(['s.user_id', 'e.employe_id', 'e.first_name', 'e.last_name', 's.organization_name'])
            ->unique('user_id')->values();
    }

    /** grup yang sah untuk saya: saya anggota, dan anggotanya cukup banyak */
    private function grupSaya(int $org, int $saya)
    {
        $anggota = $this->anggota($org);
        if ($anggota->count() < (int) config('chat.grup_minimal_anggota', 2) || !$anggota->contains('user_id', $saya)) {
            return null;
        }

        return $anggota;
    }

    private function nama($p): string
    {
        return trim($p->first_name . ' ' . $p->last_name);
    }

    private function versiTerbaru(int $org): int
    {
        return (int) DB::table('chat_group_keys')->where('org_id', $org)->max('versi');
    }

    // ---- daftar dan ringkasan ----------------------------------------------------------------

    /** grup milik saya beserta jumlah pesan belum dibaca */
    public function daftar()
    {
        $saya = (int) Auth::id();

        return response()->json(['status' => true, 'data' => self::ringkasan($saya)], 200);
    }

    /** dipakai juga oleh ChatController::belumDibaca untuk lencana dan notifikasi */
    public static function ringkasan(int $saya): array
    {
        $self = new self();
        $org = $self->orgSaya($saya);
        if ($org === null) {
            return [];
        }
        $anggota = $self->grupSaya($org, $saya);
        if (!$anggota) {
            return [];
        }
        $terakhirBaca = (int) DB::table('chat_group_reads')->where('org_id', $org)->where('user_id', $saya)->value('last_read_id');
        $belum = DB::table('chat_group_messages')->where('org_id', $org)->where('id', '>', $terakhirBaca)->where('from_user_id', '!=', $saya);
        $terakhir = DB::table('chat_group_messages')->where('org_id', $org)->orderByDesc('id')->first(['id', 'created_at']);

        return [[
            'org_id' => $org,
            'nama' => $anggota->first()->organization_name,
            'jumlah_anggota' => $anggota->count(),
            'belum_dibaca' => (int) (clone $belum)->count(),
            'terakhir_id' => (int) ($belum->max('id') ?? 0),
            'waktu_terakhir' => $terakhir ? Carbon::parse($terakhir->created_at)->toIso8601String() : null,
        ]];
    }

    // ---- kunci grup -------------------------------------------------------------------------

    /**
     * Informasi untuk membuka grup: anggota beserta kunci publiknya, kunci grup milik saya (semua versi),
     * dan apa yang perlu dilakukan browser (membagikan kunci ke anggota baru, atau memutar kunci bila ada yang sudah keluar).
     */
    public function info(int $org)
    {
        $saya = (int) Auth::id();
        $anggota = $this->grupSaya($org, $saya);
        if (!$anggota) {
            return response()->json(['status' => false, 'message' => 'Grup tidak ditemukan.'], 404);
        }
        $ids = $anggota->pluck('user_id')->all();
        $publik = DB::table('chat_keys')->whereIn('user_id', $ids)->pluck('public_key', 'user_id');
        $terbaru = $this->versiTerbaru($org);

        $pemegang = $terbaru > 0
            ? DB::table('chat_group_keys')->where('org_id', $org)->where('versi', $terbaru)->pluck('user_id')->map(fn ($x) => (int) $x)->all()
            : [];

        $kunciSaya = DB::table('chat_group_keys')->where('org_id', $org)->where('user_id', $saya)->orderBy('versi')
            ->get(['versi', 'pembungkus_user_id', 'wrapped_key', 'iv']);
        $pubPembungkus = DB::table('chat_keys')->whereIn('user_id', $kunciSaya->pluck('pembungkus_user_id')->all())->pluck('public_key', 'user_id');

        return response()->json(['status' => true, 'data' => [
            'org_id' => $org,
            'nama' => $anggota->first()->organization_name,
            'saya_id' => $saya,
            'versi_terbaru' => $terbaru,
            'anggota' => $anggota->map(fn ($p) => [
                'user_id' => (int) $p->user_id,
                'employe_id' => $p->employe_id,
                'nama' => $this->nama($p),
                'public_key' => $publik[$p->user_id] ?? null,
                'punya_kunci' => in_array((int) $p->user_id, $pemegang, true),
            ])->values(),
            'kunci_saya' => $kunciSaya->map(fn ($k) => [
                'versi' => (int) $k->versi,
                'pembungkus_user_id' => (int) $k->pembungkus_user_id,
                'pembungkus_public_key' => $pubPembungkus[$k->pembungkus_user_id] ?? null,
                'wrapped_key' => $k->wrapped_key,
                'iv' => $k->iv,
            ])->values(),
            // ada pemegang kunci terbaru yang sudah bukan anggota: kunci harus diputar agar mereka tidak bisa membaca pesan baru
            'perlu_putar' => $terbaru > 0 && count(array_diff($pemegang, array_map('intval', $ids))) > 0,
        ]], 200);
    }

    /**
     * Simpan kunci grup yang dibungkus untuk anggota. versi == terbaru: melengkapi anggota yang belum punya;
     * versi == terbaru + 1: kunci baru (grup baru atau diputar), pembuat wajib ikut menerima kuncinya.
     */
    public function simpanKunci(Request $request, int $org)
    {
        $saya = (int) Auth::id();
        $anggota = $this->grupSaya($org, $saya);
        if (!$anggota) {
            return response()->json(['status' => false, 'message' => 'Grup tidak ditemukan.'], 404);
        }
        $data = $request->validate([
            'versi' => ['required', 'integer', 'min:1'],
            'kunci' => ['required', 'array', 'min:1', 'max:200'],
            'kunci.*.user_id' => ['required', 'integer'],
            'kunci.*.wrapped_key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9+\/=]+$/'],
            'kunci.*.iv' => ['required', 'string', 'size:16', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);
        $terbaru = $this->versiTerbaru($org);
        $versi = (int) $data['versi'];
        if ($versi !== $terbaru && $versi !== $terbaru + 1) {
            return response()->json(['status' => false, 'message' => 'Versi kunci tidak sesuai, muat ulang grup.'], 409);
        }
        $idAnggota = $anggota->pluck('user_id')->map(fn ($x) => (int) $x)->all();
        $penerima = array_map(fn ($k) => (int) $k['user_id'], $data['kunci']);
        if (count(array_diff($penerima, $idAnggota)) > 0) {
            return response()->json(['status' => false, 'message' => 'Penerima kunci harus anggota grup.'], 422);
        }
        if ($versi === $terbaru + 1 && !in_array($saya, $penerima, true)) {
            return response()->json(['status' => false, 'message' => 'Pembuat kunci baru harus ikut menerima kuncinya.'], 422);
        }
        // penerima harus sudah punya kunci pesan pribadi (kunci publik), kalau tidak kunci grup tidak bisa dibuka
        $adaPublik = DB::table('chat_keys')->whereIn('user_id', $penerima)->pluck('user_id')->map(fn ($x) => (int) $x)->all();
        if (count(array_diff($penerima, $adaPublik)) > 0) {
            return response()->json(['status' => false, 'message' => 'Ada penerima yang belum mengaktifkan pesan terenkripsi.'], 422);
        }

        $baris = array_map(fn ($k) => [
            'org_id' => $org,
            'versi' => $versi,
            'user_id' => (int) $k['user_id'],
            'pembungkus_user_id' => $saya,
            'wrapped_key' => $k['wrapped_key'],
            'iv' => $k['iv'],
            'created_at' => now(),
        ], $data['kunci']);
        // insertOrIgnore: bila dua anggota membagikan bersamaan, yang pertama menang dan yang kedua dilewati
        DB::table('chat_group_keys')->insertOrIgnore($baris);

        return response()->json(['status' => true, 'versi' => $versi], 200);
    }

    // ---- pesan ------------------------------------------------------------------------------

    public function pesan(int $org)
    {
        $saya = (int) Auth::id();
        $anggota = $this->grupSaya($org, $saya);
        if (!$anggota) {
            return response()->json(['status' => false, 'message' => 'Grup tidak ditemukan.'], 404);
        }
        $nama = $anggota->mapWithKeys(fn ($p) => [(int) $p->user_id => $this->nama($p)]);
        $baris = DB::table('chat_group_messages')->where('org_id', $org)->orderByDesc('id')->limit(self::RIWAYAT)
            ->get(['id', 'from_user_id', 'versi', 'ciphertext', 'iv', 'created_at'])->reverse()->values();

        // tandai sudah dibaca sampai pesan terbaru
        if ($baris->isNotEmpty()) {
            DB::table('chat_group_reads')->updateOrInsert(
                ['org_id' => $org, 'user_id' => $saya],
                ['last_read_id' => (int) $baris->max('id'), 'updated_at' => now()]
            );
        }

        return response()->json(['status' => true, 'data' => $baris->map(fn ($m) => [
            'id' => (int) $m->id,
            'dari_user_id' => (int) $m->from_user_id,
            'dari_nama' => $nama[(int) $m->from_user_id] ?? 'Mantan anggota',
            'dari_saya' => (int) $m->from_user_id === $saya,
            'versi' => (int) $m->versi,
            'ciphertext' => $m->ciphertext,
            'iv' => $m->iv,
            'waktu' => Carbon::parse($m->created_at)->toIso8601String(),
        ])->values()], 200);
    }

    public function kirim(Request $request, int $org)
    {
        $saya = (int) Auth::id();
        if (!$this->grupSaya($org, $saya)) {
            return response()->json(['status' => false, 'message' => 'Grup tidak ditemukan.'], 404);
        }
        $data = $request->validate([
            'versi' => ['required', 'integer', 'min:1'],
            'ciphertext' => ['required', 'string', 'max:' . self::BATAS_CIPHERTEXT, 'regex:/^[A-Za-z0-9+\/=]+$/'],
            'iv' => ['required', 'string', 'size:16', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);
        $versi = (int) $data['versi'];
        // pesan baru hanya boleh memakai kunci terbaru, dan pengirim harus memegang kunci itu
        if ($versi !== $this->versiTerbaru($org)
            || !DB::table('chat_group_keys')->where('org_id', $org)->where('versi', $versi)->where('user_id', $saya)->exists()) {
            return response()->json(['status' => false, 'message' => 'Kunci grup berubah, muat ulang grup.'], 409);
        }

        $id = DB::table('chat_group_messages')->insertGetId([
            'org_id' => $org,
            'from_user_id' => $saya,
            'versi' => $versi,
            'ciphertext' => $data['ciphertext'],
            'iv' => $data['iv'],
            'created_at' => now(),
        ]);
        // pesan sendiri otomatis dianggap sudah dibaca
        DB::table('chat_group_reads')->updateOrInsert(['org_id' => $org, 'user_id' => $saya], ['last_read_id' => $id, 'updated_at' => now()]);

        return response()->json(['status' => true, 'data' => [
            'id' => (int) $id,
            'dari_user_id' => $saya,
            'dari_saya' => true,
            'versi' => $versi,
            'ciphertext' => $data['ciphertext'],
            'iv' => $data['iv'],
            'waktu' => now()->toIso8601String(),
        ]], 201);
    }
}
