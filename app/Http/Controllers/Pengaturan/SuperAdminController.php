<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\User;
use App\Services\Notifikasi\Pengaturan;
use App\Services\Notifikasi\PeringatanWa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Halaman Setting: hanya role SuperAdmin (route memakai middleware role:SuperAdmin)
class SuperAdminController extends Controller
{
    // satu-satunya penulisan yang sah untuk role super admin di database
    private const SUPER = 'SuperAdmin';

    private static function normal(string $nama): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $nama));
    }

    /** role yang dikenal: yang terdaftar di master_role ditambah semua yang sudah dipakai user, dengan jumlah pemakainya */
    private static function daftarRole(): array
    {
        $jumlah = [];
        foreach (User::select('roles')->get() as $u) {
            foreach (($u->roles ?? []) as $r) {
                $jumlah[$r] = ($jumlah[$r] ?? 0) + 1;
            }
        }
        $ket = [];
        foreach (DB::table('master_role')->get() as $m) {
            $ket[$m->nama] = $m->keterangan;
            $jumlah[$m->nama] = $jumlah[$m->nama] ?? 0;
        }
        $jumlah[self::SUPER] = $jumlah[self::SUPER] ?? 0;
        ksort($jumlah);

        return array_map(fn ($nama) => ['nama' => $nama, 'jumlah_user' => $jumlah[$nama], 'keterangan' => $ket[$nama] ?? null], array_keys($jumlah));
    }

    public function roles()
    {
        return response()->json(['status' => true, 'data' => self::daftarRole()], 200);
    }

    public function tambahRole(Request $request)
    {
        $nama = trim((string) $request->input('nama'));
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,39}$/', $nama)) {
            return response()->json(['status' => false, 'message' => 'Nama role 2-40 karakter: huruf, angka atau garis bawah, tanpa spasi, diawali huruf.'], 422);
        }
        $baru = self::normal($nama);
        foreach (self::daftarRole() as $r) {
            if (self::normal($r['nama']) === $baru) {
                return response()->json(['status' => false, 'message' => $baru === self::normal(self::SUPER)
                    ? 'Role super admin hanya boleh ditulis persis "' . self::SUPER . '".'
                    : 'Role sudah ada: ' . $r['nama']], 422);
            }
        }
        DB::table('master_role')->insert([
            'nama' => $nama,
            'keterangan' => mb_substr(trim((string) $request->input('keterangan')), 0, 255) ?: null,
            'dibuat_oleh' => Employe::employeId(),
            'created_at' => now(),
        ]);
        Log::info('Role baru ditambahkan', ['role' => $nama, 'oleh' => Employe::employeId()]);

        return response()->json(['status' => true, 'data' => self::daftarRole()], 201);
    }

    public function users(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $baris = User::query()
            // hanya pegawai aktif
            ->join('struktur_lengkap_oke as s', 's.employe_id', '=', 'users.employe_id')
            ->where('s.employe_active', 1)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('users.email', 'like', "%{$q}%")->orWhere('s.first_name', 'like', "%{$q}%")))
            ->orderBy('s.first_name')
            ->select('users.id', 'users.email', 'users.employe_id', 'users.roles', 's.first_name as nama', 's.position_name as jabatan')
            ->paginate(20);

        return response()->json(['status' => true, 'data' => $baris], 200);
    }

    // ganti seluruh role satu user (menambah, mencabut) dalam sekali simpan
    public function ubahRoleUser(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User tidak ditemukan.'], 404);
        }
        $roles = array_values(array_unique(array_map('strval', (array) $request->input('roles', []))));
        $dikenal = array_column(self::daftarRole(), 'nama');
        $asing = array_values(array_diff($roles, $dikenal));
        if ($asing) {
            return response()->json(['status' => false, 'message' => 'Role belum terdaftar: ' . implode(', ', $asing)], 422);
        }
        if (!$roles) {
            return response()->json(['status' => false, 'message' => 'User minimal punya satu role.'], 422);
        }
        if ((string) $user->employe_id === (string) Employe::employeId() && in_array(self::SUPER, $user->roles ?? [], true) && !in_array(self::SUPER, $roles, true)) {
            return response()->json(['status' => false, 'message' => 'Anda tidak bisa mencabut role ' . self::SUPER . ' dari akun sendiri.'], 422);
        }
        $lama = $user->roles ?? [];
        $user->roles = $roles;
        $user->save();
        Log::info('Role user diubah Super Admin', [
            'user' => $user->id, 'employe_id' => $user->employe_id, 'oleh' => Employe::employeId(),
            'ditambah' => array_values(array_diff($roles, $lama)), 'dicabut' => array_values(array_diff($lama, $roles)),
        ]);

        return response()->json(['status' => true, 'data' => ['id' => $user->id, 'roles' => $user->roles]], 200);
    }

    public function wa()
    {
        $antre = DB::connection('notif')->table('notification_deliveries')->where('channel', 'wa')->where('status', 'pending')->count();

        $g = Pengaturan::gateway();

        return response()->json(['status' => true, 'data' => [
            'aktif' => !Pengaturan::dijeda() && $g['aktif'],
            'dijeda' => Pengaturan::dijeda(),
            'gateway' => ['hidup' => $g['hidup'], 'terhubung' => $g['terhubung'], 'http' => $g['http'], 'pesan' => $g['pesan'], 'respons' => $g['respons']],
            'notif_dasar_aktif' => (bool) config('notifikasi.aktif'),
            'antre' => $antre,
        ]], 200);
    }

    // jeda atau lanjutkan pengiriman; status gateway sendiri tidak bisa diubah dari sini
    public function ubahWa(Request $request)
    {
        $jeda = !$request->boolean('aktif');
        $sebelum = Pengaturan::dijeda();
        Pengaturan::setDijeda($jeda, Employe::employeId());
        if ($jeda && !$sebelum) {
            $oleh = DB::table('struktur_lengkap_oke')->where('employe_id', Employe::employeId())->value('first_name') ?: Employe::employeId();
            PeringatanWa::kirim('dijeda', 'Pengiriman WhatsApp notifikasi dijeda', "Pengiriman WhatsApp notifikasi dijeda oleh {$oleh} lewat menu Setting. Pesan yang antre tertahan sampai dilanjutkan lagi.");
        }
        Log::info('Jeda WhatsApp notifikasi diubah', ['dijeda' => $jeda, 'oleh' => Employe::employeId()]);

        return $this->wa();
    }

    // daftar pengiriman notifikasi per kanal: belum terkirim (pending), terkirim, gagal, dilewati
    public function pengiriman(Request $request)
    {
        $kanal = $request->query('kanal', 'wa');
        $status = $request->query('status');
        $peta = ['belum' => 'pending', 'terkirim' => 'sent', 'gagal' => 'failed', 'dilewati' => 'skipped'];

        $dasar = DB::connection('notif')->table('notification_deliveries as d')
            ->join('notifications as n', 'n.id', '=', 'd.notification_id')
            ->when(in_array($kanal, ['wa', 'webpush', 'fcm'], true), fn ($q) => $q->where('d.channel', $kanal));

        $ringkas = (clone $dasar)->select('d.status', DB::raw('count(*) as jumlah'))->groupBy('d.status')->pluck('jumlah', 'status');

        $data = (clone $dasar)
            ->when(isset($peta[$status]), fn ($q) => $q->where('d.status', $peta[$status]))
            ->orderByDesc('d.id')
            ->select('d.id', 'd.channel', 'd.status', 'd.target', 'd.attempts', 'd.error', 'd.sent_at', 'd.created_at',
                'n.judul', 'n.isi', 'n.event', 'n.modul', 'n.recipient')
            ->paginate(25);

        $nama = DB::table('struktur_lengkap_oke')->whereIn('employe_id', $data->pluck('recipient')->unique())->pluck('first_name', 'employe_id');
        $data->getCollection()->transform(function ($r) use ($nama) {
            $r->penerima = $nama[$r->recipient] ?? $r->recipient;

            return $r;
        });

        return response()->json(['status' => true, 'ringkasan' => [
            'belum' => (int) ($ringkas['pending'] ?? 0), 'terkirim' => (int) ($ringkas['sent'] ?? 0),
            'gagal' => (int) ($ringkas['failed'] ?? 0), 'dilewati' => (int) ($ringkas['skipped'] ?? 0),
        ], 'data' => $data], 200);
    }
}
