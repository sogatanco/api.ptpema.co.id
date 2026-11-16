<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pesan antar akun + daftar pengguna dengan status online.
 * Online = punya sesi login yang mengakses API dalam 2 menit terakhir; selain itu hanya ditampilkan "aktif X lalu".
 */
class ChatController extends Controller
{
    private const MENIT_ONLINE = 2;
    private const BATAS_PESAN = 2000;
    private const RIWAYAT = 100;

    /** semua pegawai aktif (kecuali diri sendiri), yang online di atas, lalu urut aktivitas terakhir */
    public function users()
    {
        $saya = (int) Auth::id();
        $pegawai = DB::table('employees_hr')->where('employe_active', 1)->whereNotNull('user_id')->where('user_id', '!=', $saya)
            ->get(['user_id', 'employe_id', 'first_name', 'last_name']);
        $ids = $pegawai->pluck('user_id')->all();

        // aktivitas terakhir dari sesi mana pun, dan apakah ada sesi yang masih login dan baru saja aktif
        $batas = now()->subMinutes(self::MENIT_ONLINE);
        $sesi = DB::table('user_sessions')->whereIn('user_id', $ids)
            ->selectRaw('user_id, MAX(last_active_at) AS terakhir, MAX(CASE WHEN revoked_at IS NULL AND last_active_at >= ? THEN 1 ELSE 0 END) AS online', [$batas])
            ->groupBy('user_id')->get()->keyBy('user_id');
        $belumDibaca = DB::table('chat_messages')->where('to_user_id', $saya)->whereNull('read_at')
            ->selectRaw('from_user_id, COUNT(*) AS jumlah')->groupBy('from_user_id')->pluck('jumlah', 'from_user_id');
        $struktur = DB::table('struktur_lengkap_oke')->whereIn('user_id', $ids)->get(['user_id', 'organization_name', 'position_name'])->keyBy('user_id');

        $daftar = $pegawai->map(function ($p) use ($sesi, $belumDibaca, $struktur) {
            $s = $sesi->get($p->user_id);
            $st = $struktur->get($p->user_id);
            $terakhir = $s && $s->terakhir ? \Carbon\Carbon::parse($s->terakhir)->toIso8601String() : null;

            return [
                'user_id' => (int) $p->user_id,
                'employe_id' => $p->employe_id,
                'nama' => trim($p->first_name . ' ' . $p->last_name),
                'divisi' => $st->organization_name ?? null,
                'jabatan' => $st->position_name ?? null,
                'online' => $s && (int) $s->online === 1,
                'terakhir_aktif' => $terakhir,
                'belum_dibaca' => (int) ($belumDibaca[$p->user_id] ?? 0),
            ];
        })->sort(function ($a, $b) {
            // online dulu, lalu yang punya pesan belum dibaca, lalu aktivitas terbaru, lalu nama
            return [$b['online'], $b['belum_dibaca'] > 0, (string) $b['terakhir_aktif'], $a['nama']]
                <=> [$a['online'], $a['belum_dibaca'] > 0, (string) $a['terakhir_aktif'], $b['nama']];
        })->values();

        return response()->json([
            'status' => true,
            'menit_online' => self::MENIT_ONLINE,
            'jumlah_online' => $daftar->where('online', true)->count(),
            'belum_dibaca' => (int) $daftar->sum('belum_dibaca'),
            'data' => $daftar,
        ], 200);
    }

    /** ringkasan pesan belum dibaca per pengirim (ringan, dipanggil berkala untuk lencana dan notifikasi) */
    public function belumDibaca()
    {
        $saya = (int) Auth::id();
        $per = DB::table('chat_messages')->where('to_user_id', $saya)->whereNull('read_at')
            ->selectRaw('from_user_id, COUNT(*) AS jumlah, MAX(id) AS terakhir_id')->groupBy('from_user_id')->get();
        $isi = DB::table('chat_messages')->whereIn('id', $per->pluck('terakhir_id')->all())->get(['id', 'body', 'created_at'])->keyBy('id');
        $pegawai = DB::table('employees_hr')->whereIn('user_id', $per->pluck('from_user_id')->all())
            ->get(['user_id', 'employe_id', 'first_name', 'last_name'])->keyBy('user_id');

        $data = $per->map(function ($r) use ($isi, $pegawai) {
            $p = $pegawai->get($r->from_user_id);
            $m = $isi->get($r->terakhir_id);

            return [
                'user_id' => (int) $r->from_user_id,
                'employe_id' => $p->employe_id ?? null,
                'nama' => $p ? trim($p->first_name . ' ' . $p->last_name) : 'Pengguna #' . $r->from_user_id,
                'jumlah' => (int) $r->jumlah,
                'terakhir_id' => (int) $r->terakhir_id,
                'pesan_terakhir' => $m ? mb_strimwidth((string) $m->body, 0, 90, '…') : '',
                'waktu' => $m ? \Carbon\Carbon::parse($m->created_at)->toIso8601String() : null,
            ];
        })->sortByDesc('terakhir_id')->values();

        return response()->json(['status' => true, 'total' => (int) $data->sum('jumlah'), 'data' => $data], 200);
    }
    /** percakapan dengan satu akun; pesan masuk dari akun itu ditandai sudah dibaca */
    public function percakapan(Request $request, int $userId)
    {
        $saya = (int) Auth::id();
        if (!$this->akunAda($userId) || $userId === $saya) {
            return response()->json(['status' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        DB::table('chat_messages')->where('from_user_id', $userId)->where('to_user_id', $saya)->whereNull('read_at')
            ->update(['read_at' => now()]);

        $q = DB::table('chat_messages')->where(function ($w) use ($saya, $userId) {
            $w->where(fn ($x) => $x->where('from_user_id', $saya)->where('to_user_id', $userId))
                ->orWhere(fn ($x) => $x->where('from_user_id', $userId)->where('to_user_id', $saya));
        });
        $pesan = $q->orderByDesc('id')->limit(self::RIWAYAT)->get(['id', 'from_user_id', 'body', 'created_at'])->reverse()->values()
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'dari_saya' => (int) $m->from_user_id === $saya,
                'isi' => $m->body,
                'waktu' => \Carbon\Carbon::parse($m->created_at)->toIso8601String(),
            ]);

        return response()->json(['status' => true, 'data' => $pesan], 200);
    }

    public function kirim(Request $request, int $userId)
    {
        $saya = (int) Auth::id();
        $request->validate(['isi' => ['required', 'string', 'max:' . self::BATAS_PESAN]]);
        if (!$this->akunAda($userId) || $userId === $saya) {
            return response()->json(['status' => false, 'message' => 'Akun tujuan tidak ditemukan.'], 404);
        }
        $isi = trim((string) $request->input('isi'));
        if ($isi === '') {
            return response()->json(['status' => false, 'message' => 'Pesan kosong.'], 422);
        }

        $id = DB::table('chat_messages')->insertGetId([
            'from_user_id' => $saya,
            'to_user_id' => $userId,
            'body' => $isi,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => true, 'data' => [
            'id' => (int) $id,
            'dari_saya' => true,
            'isi' => $isi,
            'waktu' => now()->toIso8601String(),
        ]], 201);
    }

    private function akunAda(int $userId): bool
    {
        return DB::table('employees_hr')->where('user_id', $userId)->where('employe_active', 1)->exists();
    }
}
