<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan biaya SPPD per ketetapan (hanya SuperAdmin).
 *
 * Satu ketetapan = satu dasar hukum (nomor + tanggal berlaku) dengan seperangkat tarif:
 *  - biaya_sppds : tiket, uang makan (um), uang saku (us), transport lokal (tr), hotel, per golongan x kategori
 *  - master_times: faktor uang makan/saku untuk hari berangkat dan pulang menurut jam
 * SPPD menyimpan ketetapan yang aktif saat dibuat (sppds.ketetapan), lalu semua view biaya memakai ketetapan itu.
 * Karena view menghitung ulang setiap dibuka, mengubah tarif ketetapan yang sudah dipakai ikut mengubah total SPPD lama:
 * untuk itu perubahan harus dikonfirmasi, dan cara yang benar adalah membuat ketetapan baru lalu mengaktifkannya.
 */
class BiayaSppdController extends Controller
{
    private const STATUS_AKTIF = 'active';

    private const STATUS_NONAKTIF = 'inactive';

    private const KOLOM_BIAYA = ['tiket', 'um', 'us', 'tr', 'hotel'];

    private function db()
    {
        return DB::connection('mysql4');
    }

    private function gagal(string $pesan, int $kode = 422): void
    {
        abort($kode, $pesan);
    }

    private function ketetapan(int $id)
    {
        $k = $this->db()->table('ketetapan_sppds')->where('id', $id)->first();
        if (!$k) {
            $this->gagal('Ketetapan tidak ditemukan.', 404);
        }

        return $k;
    }

    private function jumlahSppd(int $id): int
    {
        return (int) $this->db()->table('sppds')->where('ketetapan', $id)->count();
    }

    private function format($k): array
    {
        return [
            'id' => (int) $k->id,
            'dasar_ketetapan' => $k->dasar_ketetapan,
            'berlaku_mulai' => $k->berlaku_mulai,
            'status' => $k->status,
            'aktif' => $k->status === self::STATUS_AKTIF,
            'jumlah_sppd' => $this->jumlahSppd((int) $k->id),
            'jumlah_biaya' => (int) $this->db()->table('biaya_sppds')->where('ketetapan', $k->id)->count(),
        ];
    }

    /** Daftar ketetapan. */
    public function index()
    {
        $data = $this->db()->table('ketetapan_sppds')->orderByDesc('berlaku_mulai')->orderByDesc('id')->get()->map(fn ($k) => $this->format($k))->values();

        return response()->json(['status' => true, 'data' => $data], 200);
    }

    /** Detail satu ketetapan: tarif per golongan x kategori dan faktor waktu. */
    public function show($id)
    {
        $k = $this->ketetapan((int) $id);
        $db = $this->db();
        $golongan = $db->table('golongan_sppds as g')->join('pihaks as p', 'p.id', '=', 'g.id_pihak')
            ->orderBy('g.id_pihak')->orderBy('g.id')->get(['g.id', 'g.golongan', 'g.id_pihak', 'p.pihak_name'])
            ->map(fn ($g) => ['id' => (int) $g->id, 'nama' => $g->golongan, 'id_pihak' => (int) $g->id_pihak, 'pihak' => $g->pihak_name])->values();
        $kategori = $db->table('categori_sppds')->orderBy('id')->get(['id', 'categori_sppd'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'nama' => $c->categori_sppd])->values();
        $biaya = $db->table('biaya_sppds')->where('ketetapan', $k->id)->orderBy('id_golongan')->orderBy('id_category')
            ->get(['id', 'id_golongan', 'id_category', 'tiket', 'um', 'us', 'tr', 'hotel'])
            ->map(fn ($b) => [
                'id' => (int) $b->id, 'id_golongan' => (int) $b->id_golongan, 'id_category' => (int) $b->id_category,
                'tiket' => (int) $b->tiket, 'um' => (int) $b->um, 'us' => (int) $b->us, 'tr' => (int) $b->tr, 'hotel' => (int) $b->hotel,
            ])->values();
        $waktu = $db->table('master_times')->where('ketetapan', $k->id)->orderBy('start')
            ->get(['id', 'start', 'end', 'rate_pergi', 'rate_pulang'])
            ->map(fn ($w) => [
                'id' => (int) $w->id, 'start' => substr($w->start, 0, 5), 'end' => substr($w->end, 0, 5),
                'rate_pergi' => (float) $w->rate_pergi, 'rate_pulang' => (float) $w->rate_pulang,
            ])->values();

        return response()->json(['status' => true, 'data' => [
            'ketetapan' => $this->format($k),
            'golongan' => $golongan,
            'kategori' => $kategori,
            'biaya' => $biaya,
            'waktu' => $waktu,
        ]], 200);
    }

    /** Membuat ketetapan baru (nonaktif) dengan menyalin tarif dan faktor waktu dari ketetapan lain. */
    public function store(Request $request)
    {
        $request->validate([
            'dasar_ketetapan' => 'required|string|max:255',
            'berlaku_mulai' => 'required|date',
            'salin_dari' => 'required|integer',
        ]);
        $sumber = $this->ketetapan((int) $request->input('salin_dari'));
        $dasar = trim($request->input('dasar_ketetapan'));
        if ($this->db()->table('ketetapan_sppds')->where('dasar_ketetapan', $dasar)->exists()) {
            $this->gagal('Dasar ketetapan itu sudah ada.');
        }

        $baru = $this->db()->transaction(function () use ($request, $sumber, $dasar) {
            $db = $this->db();
            $id = $db->table('ketetapan_sppds')->insertGetId([
                'dasar_ketetapan' => $dasar,
                'berlaku_mulai' => $request->input('berlaku_mulai'),
                'status' => self::STATUS_NONAKTIF,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($db->table('biaya_sppds')->where('ketetapan', $sumber->id)->get() as $b) {
                $db->table('biaya_sppds')->insert([
                    'id_golongan' => $b->id_golongan, 'id_category' => $b->id_category, 'ketetapan' => $id,
                    'tiket' => $b->tiket, 'um' => $b->um, 'us' => $b->us, 'tr' => $b->tr, 'hotel' => $b->hotel,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($db->table('master_times')->where('ketetapan', $sumber->id)->get() as $w) {
                $db->table('master_times')->insert([
                    'start' => $w->start, 'end' => $w->end, 'rate_pergi' => $w->rate_pergi, 'rate_pulang' => $w->rate_pulang,
                    'ketetapan' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $id;
        });

        return response()->json(['status' => true, 'message' => 'Ketetapan dibuat (belum aktif). Sesuaikan tarifnya lalu aktifkan.', 'data' => ['id' => $baru]], 200);
    }

    /** Mengubah dasar/tanggal berlaku, tarif dan faktor waktu. Ketetapan yang sudah dipakai SPPD butuh konfirmasi. */
    public function update(Request $request, $id)
    {
        $k = $this->ketetapan((int) $id);
        $request->validate([
            'dasar_ketetapan' => 'required|string|max:255',
            'berlaku_mulai' => 'required|date',
            'biaya' => 'nullable|array',
            'waktu' => 'nullable|array',
        ]);
        $dasar = trim($request->input('dasar_ketetapan'));
        if ($this->db()->table('ketetapan_sppds')->where('dasar_ketetapan', $dasar)->where('id', '<>', $k->id)->exists()) {
            $this->gagal('Dasar ketetapan itu sudah dipakai ketetapan lain.');
        }

        $biayaIn = (array) $request->input('biaya', []);
        $waktuIn = (array) $request->input('waktu', []);
        $pakai = $this->jumlahSppd((int) $k->id);
        if ($pakai > 0 && (count($biayaIn) > 0 || count($waktuIn) > 0) && !$request->boolean('konfirmasi')) {
            $this->gagal("Ketetapan ini sudah dipakai $pakai SPPD. Mengubah tarifnya ikut mengubah total biaya SPPD tersebut. Buat ketetapan baru, atau konfirmasi bila memang perlu.", 409);
        }

        $db = $this->db();
        $idBiaya = $db->table('biaya_sppds')->where('ketetapan', $k->id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $idWaktu = $db->table('master_times')->where('ketetapan', $k->id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $ubahBiaya = [];
        foreach ($biayaIn as $b) {
            $b = (array) $b;
            $bid = (int) ($b['id'] ?? 0);
            if (!in_array($bid, $idBiaya, true)) {
                $this->gagal('Baris tarif tidak termasuk ketetapan ini.');
            }
            $nilai = [];
            foreach (self::KOLOM_BIAYA as $kol) {
                $v = $b[$kol] ?? null;
                if (!is_numeric($v) || $v < 0 || $v > 1000000000 || (float) $v != floor((float) $v)) {
                    $this->gagal("Nilai $kol harus bilangan bulat 0 sampai 1.000.000.000.");
                }
                $nilai[$kol] = (int) $v;
            }
            $ubahBiaya[$bid] = $nilai;
        }
        $ubahWaktu = [];
        foreach ($waktuIn as $w) {
            $w = (array) $w;
            $wid = (int) ($w['id'] ?? 0);
            if (!in_array($wid, $idWaktu, true)) {
                $this->gagal('Baris faktor waktu tidak termasuk ketetapan ini.');
            }
            foreach (['rate_pergi', 'rate_pulang'] as $kol) {
                $v = $w[$kol] ?? null;
                if (!is_numeric($v) || $v < 0 || $v > 5) {
                    $this->gagal('Faktor waktu harus angka 0 sampai 5.');
                }
            }
            $ubahWaktu[$wid] = ['rate_pergi' => round((float) $w['rate_pergi'], 2), 'rate_pulang' => round((float) $w['rate_pulang'], 2)];
        }

        $db->transaction(function () use ($db, $k, $dasar, $request, $ubahBiaya, $ubahWaktu) {
            $db->table('ketetapan_sppds')->where('id', $k->id)->update([
                'dasar_ketetapan' => $dasar, 'berlaku_mulai' => $request->input('berlaku_mulai'), 'updated_at' => now(),
            ]);
            foreach ($ubahBiaya as $bid => $nilai) {
                $db->table('biaya_sppds')->where('id', $bid)->update($nilai + ['updated_at' => now()]);
            }
            foreach ($ubahWaktu as $wid => $nilai) {
                $db->table('master_times')->where('id', $wid)->update($nilai + ['updated_at' => now()]);
            }
        });

        return response()->json(['status' => true, 'message' => 'Tersimpan.'], 200);
    }

    /** Menjadikan ketetapan ini satu-satunya yang aktif (dipakai SPPD baru). */
    public function aktifkan($id)
    {
        $k = $this->ketetapan((int) $id);
        $db = $this->db();
        $perlu = (int) $db->table('golongan_sppds')->count() * (int) $db->table('categori_sppds')->count();
        $ada = (int) $db->table('biaya_sppds')->where('ketetapan', $k->id)->count();
        if ($ada !== $perlu) {
            $this->gagal("Tarif belum lengkap: $ada dari $perlu baris (golongan x kategori).");
        }
        if ($db->table('master_times')->where('ketetapan', $k->id)->count() < 1) {
            $this->gagal('Faktor waktu belum ada untuk ketetapan ini.');
        }
        $db->transaction(function () use ($db, $k) {
            $db->table('ketetapan_sppds')->where('id', '<>', $k->id)->update(['status' => self::STATUS_NONAKTIF, 'updated_at' => now()]);
            $db->table('ketetapan_sppds')->where('id', $k->id)->update(['status' => self::STATUS_AKTIF, 'updated_at' => now()]);
        });

        return response()->json(['status' => true, 'message' => 'Ketetapan diaktifkan. SPPD baru memakai tarif ini.'], 200);
    }

    /** Menghapus ketetapan yang tidak aktif dan belum dipakai SPPD mana pun. */
    public function destroy($id)
    {
        $k = $this->ketetapan((int) $id);
        if ($k->status === self::STATUS_AKTIF) {
            $this->gagal('Ketetapan yang sedang aktif tidak bisa dihapus.');
        }
        if ($this->jumlahSppd((int) $k->id) > 0) {
            $this->gagal('Ketetapan ini sudah dipakai SPPD, tidak bisa dihapus.');
        }
        $db = $this->db();
        $db->transaction(function () use ($db, $k) {
            $db->table('biaya_sppds')->where('ketetapan', $k->id)->delete();
            $db->table('master_times')->where('ketetapan', $k->id)->delete();
            $db->table('ketetapan_sppds')->where('id', $k->id)->delete();
        });

        return response()->json(['status' => true, 'message' => 'Ketetapan dihapus.'], 200);
    }
}
