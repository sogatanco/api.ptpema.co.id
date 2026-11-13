<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Setting > Alur Approval: aturan alur persetujuan SPPD dan surat (ADM) yang dibaca trigger lewat
 * ptpemaco_esign.bangun_alur (lihat db/alur_approval.sql). Hanya role SuperAdmin (route memakai middleware role:SuperAdmin).
 *
 * alur_aturan : satu aturan = kelompok id_base (SPPD: id_base pegawai tujuan atau '-'; surat: id_base penandatangan atau '*', boleh per divisi)
 * alur_langkah: langkah berurutan (verifikasi_biaya / paraf / sign) beserta sumber orangnya
 */
class AlurApprovalController extends Controller
{
    private const TIPE = ['verifikasi_biaya', 'paraf', 'sign'];

    private const SUMBER = [
        'sppd' => ['posisi', 'id_base', 'puk', 'direktur_divisi', 'dir_terkait'],
        'surat' => ['posisi', 'id_base', 'puk', 'direktur_divisi', 'penandatangan'],
    ];

    // aturan tambahan SPPD (satu aturan per modul, hanya diubah): langkah ditambahkan ke dokumen SPPD yang sudah ada
    // real_baru: pengajuan realisasi baru, real_ubah: perubahan realisasi, hotel30: klaim hotel 30%, tambah_hari: penambahan hari
    private const TAMBAHAN = ['real_baru', 'real_ubah', 'hotel30', 'tambah_hari'];

    private const TIPE_TAMBAHAN = ['check_document', 'verif_document', 'verif_realisasi', 'pemohon', 'approval_hotel', 'extend'];

    private const SUMBER_TAMBAHAN = ['posisi', 'id_base', 'puk_divisi', 'ttd_dokumen', 'pemohon_sppd'];

    private function db()
    {
        return DB::connection('esign');
    }

    private function gagal(string $pesan, int $kode = 422): never
    {
        throw new HttpResponseException(response()->json(['status' => false, 'success' => false, 'message' => $pesan], $kode));
    }

    private function cekModul(string $modul): void
    {
        if (!in_array($modul, array_merge(['sppd', 'surat'], self::TAMBAHAN), true)) {
            $this->gagal('Modul tidak dikenal.', 404);
        }
    }

    /** pilihan untuk form: jabatan yang ada pemegangnya, id_base, divisi, pegawai (untuk uji alur) */
    public function opsi()
    {
        $struktur = DB::table('struktur_lengkap_oke')->select('employe_id', 'first_name', 'position_id', 'position_name', 'id_base', 'organization_id')->get();
        $jabatan = $struktur->groupBy('position_id')->map(fn ($g) => [
            'id' => (int) $g[0]->position_id,
            'nama' => $g[0]->position_name,
            'pemegang' => $g->pluck('first_name')->filter()->values()->all(),
        ])->sortBy('nama')->values();

        $base = DB::table('base_positions')->orderBy('id')->get(['id', 'base_name'])->map(fn ($b) => [
            'id' => (int) $b->id,
            'nama' => $b->base_name,
            'jumlah' => $struktur->where('id_base', $b->id)->count(),
        ])->values();

        $divisi = DB::table('divisions')->orderBy('organization_name')->get(['organization_id', 'organization_name'])->map(fn ($d) => [
            'id' => (int) $d->organization_id,
            'nama' => $d->organization_name,
        ])->values();

        $pegawai = $struktur->sortBy('first_name')->map(fn ($p) => [
            'id' => $p->employe_id,
            'nama' => $p->first_name,
            'jabatan' => $p->position_name,
            'id_base' => $p->id_base === null ? null : (int) $p->id_base,
            'divisi' => (int) $p->organization_id,
        ])->values();

        // semua jabatan di master HR (termasuk yang sedang kosong), untuk aturan tambahan yang boleh menunjuk jabatan tanpa pemegang
        $pemegang = $struktur->groupBy('position_id')->map(fn ($g) => $g->pluck('first_name')->filter()->values()->all());
        $jabatanSemua = DB::table('ptpemaco_hr_production.positions')->orderBy('position_name')->get(['id', 'position_name'])->map(fn ($j) => [
            'id' => (int) $j->id,
            'nama' => $j->position_name,
            'pemegang' => $pemegang[$j->id] ?? [],
        ])->values();

        // contoh SPPD untuk menguji alur tambahan
        $sppdContoh = DB::connection('mysql4')->table('sppds')->orderByDesc('id')->limit(200)->get(['id', 'nomor_dokumen', 'nama'])->map(fn ($s) => [
            'id' => (int) $s->id,
            'nomor' => $s->nomor_dokumen,
            'nama' => $s->nama,
        ])->values();

        return response()->json(['status' => true, 'data' => [
            'jabatan' => $jabatan,
            'jabatan_semua' => $jabatanSemua,
            'base' => $base,
            'divisi' => $divisi,
            'pegawai' => $pegawai,
            'sppd_contoh' => $sppdContoh,
        ]], 200);
    }

    private function format($aturan, $langkah, array $divisi): array
    {
        return [
            'id' => (int) $aturan->id,
            'modul' => $aturan->modul,
            'nama' => $aturan->nama,
            'id_base' => array_values(array_filter(explode(',', (string) $aturan->id_base), fn ($v) => $v !== '')),
            'id_divisi' => $aturan->id_divisi === null ? null : (int) $aturan->id_divisi,
            'nama_divisi' => $aturan->id_divisi === null ? null : ($divisi[(int) $aturan->id_divisi] ?? ('Divisi ' . $aturan->id_divisi)),
            'hapus_tipe' => array_values(array_filter(explode(',', (string) ($aturan->hapus_tipe ?? '')), fn ($v) => $v !== '')),
            'keterangan' => $aturan->keterangan,
            'aktif' => (bool) $aturan->aktif,
            'diubah_oleh' => $aturan->diubah_oleh,
            'updated_at' => $aturan->updated_at,
            'langkah' => $langkah->map(fn ($l) => [
                'tipe' => $l->tipe,
                'sumber' => $l->sumber,
                'nilai' => $l->nilai === null ? null : (int) $l->nilai,
                'urutan_sumber' => (int) $l->urutan_sumber,
                'cadangan_sumber' => $l->cadangan_sumber,
                'cadangan_nilai' => $l->cadangan_nilai === null ? null : (int) $l->cadangan_nilai,
                'lewati_jika_sama' => $l->lewati_jika_sama,
                'lewati_ganda' => (bool) $l->lewati_ganda,
                'nama_hr' => (bool) $l->nama_hr,
                'notif' => (bool) $l->notif,
            ])->values()->all(),
        ];
    }

    private function semua(string $modul): array
    {
        $db = $this->db();
        $divisi = DB::table('divisions')->pluck('organization_name', 'organization_id')->mapWithKeys(fn ($v, $k) => [(int) $k => $v])->all();
        $langkah = $db->table('alur_langkah')->orderBy('urutan')->orderBy('id')->get()->groupBy('id_aturan');

        return $db->table('alur_aturan')->where('modul', $modul)->orderByRaw('id_divisi IS NOT NULL')->orderBy('id_divisi')->orderBy('id')->get()
            ->map(fn ($a) => $this->format($a, $langkah->get($a->id, collect()), $divisi))->values()->all();
    }

    public function daftar(string $modul)
    {
        $this->cekModul($modul);

        return response()->json(['status' => true, 'data' => $this->semua($modul)], 200);
    }

    /** validasi dan rapikan isian aturan; mengembalikan [kolom aturan, daftar langkah] */
    private function bersihkan(Request $request, string $modul, ?int $idSendiri): array
    {
        $nama = trim((string) $request->input('nama'));
        if ($nama === '' || mb_strlen($nama) > 150) {
            $this->gagal('Nama aturan wajib diisi (maksimal 150 karakter).');
        }
        $ket = trim((string) $request->input('keterangan'));
        if (mb_strlen($ket) > 255) {
            $this->gagal('Keterangan maksimal 255 karakter.');
        }
        if (in_array($modul, self::TAMBAHAN, true)) {
            return $this->bersihkanTambahan($request, $modul, $nama, $ket);
        }

        // kelompok id_base
        $semuaBase = DB::table('base_positions')->pluck('id')->map(fn ($v) => (string) $v)->all();
        $khusus = $modul === 'sppd' ? '-' : '*';
        $diizinkan = array_merge($semuaBase, [$khusus]);
        $base = array_values(array_unique(array_map('strval', (array) $request->input('id_base', []))));
        if (!$base) {
            $this->gagal($modul === 'sppd' ? 'Pilih minimal satu jenjang pegawai tujuan.' : 'Pilih minimal satu jenjang penandatangan.');
        }
        foreach ($base as $b) {
            if (!in_array($b, $diizinkan, true)) {
                $this->gagal('Jenjang tidak dikenal: ' . $b);
            }
        }
        usort($base, fn ($x, $y) => (is_numeric($x) && is_numeric($y)) ? ((int) $x <=> (int) $y) : strcmp($x, $y));

        $idDivisi = null;
        if ($modul === 'surat' && $request->filled('id_divisi')) {
            $idDivisi = (int) $request->input('id_divisi');
            if (!DB::table('divisions')->where('organization_id', $idDivisi)->exists()) {
                $this->gagal('Divisi tidak ditemukan.');
            }
        }

        // satu jenjang hanya boleh ada di satu aturan (per divisi untuk surat)
        $lain = $this->db()->table('alur_aturan')->where('modul', $modul)
            ->when($idSendiri, fn ($q) => $q->where('id', '!=', $idSendiri))
            ->when($idDivisi === null, fn ($q) => $q->whereNull('id_divisi'), fn ($q) => $q->where('id_divisi', $idDivisi))
            ->get(['nama', 'id_base']);
        foreach ($lain as $a) {
            $dipakai = array_intersect($base, explode(',', (string) $a->id_base));
            if ($dipakai) {
                $this->gagal('Jenjang ' . implode(', ', $dipakai) . ' sudah dipakai aturan "' . $a->nama . '"' . ($idDivisi === null ? '' : ' di divisi ini') . '.');
            }
        }

        // langkah
        $punyaPemegang = DB::table('struktur_lengkap_oke')->distinct()->pluck('position_id')->map(fn ($v) => (int) $v)->all();
        $mentah = array_values((array) $request->input('langkah', []));
        if (count($mentah) < 1 || count($mentah) > 15) {
            $this->gagal('Alur harus punya 1 sampai 15 langkah.');
        }
        $langkah = [];
        foreach ($mentah as $i => $l) {
            $no = $i + 1;
            $l = (array) $l;
            $tipe = (string) ($l['tipe'] ?? '');
            if (!in_array($tipe, self::TIPE, true) || ($tipe === 'verifikasi_biaya' && $modul !== 'sppd')) {
                $this->gagal("Langkah $no: jenis langkah tidak valid.");
            }
            [$sumber, $nilai, $off] = $this->sumber($modul, $l['sumber'] ?? null, $l['nilai'] ?? null, $l['urutan_sumber'] ?? 0, $semuaBase, $punyaPemegang, "Langkah $no");

            $cadS = $l['cadangan_sumber'] ?? null;
            $cadN = null;
            if ($cadS !== null && $cadS !== '') {
                if ($sumber !== 'dir_terkait') {
                    $this->gagal("Langkah $no: pengganti hanya dipakai bila sumber utama \"direktur terkait\".");
                }
                [$cadS, $cadN] = $this->sumber($modul, $cadS, $l['cadangan_nilai'] ?? null, 0, $semuaBase, $punyaPemegang, "Langkah $no (pengganti)");
                if (in_array($cadS, ['dir_terkait'], true)) {
                    $this->gagal("Langkah $no: pengganti tidak boleh sama dengan sumber utama.");
                }
            } else {
                $cadS = null;
            }

            $lewati = $l['lewati_jika_sama'] ?? null;
            $lewati = ($lewati === '' ? null : $lewati);
            if ($lewati !== null && $lewati !== ($modul === 'sppd' ? 'pengaju' : 'penandatangan')) {
                $this->gagal("Langkah $no: aturan lewati tidak valid.");
            }
            $namaHr = !empty($l['nama_hr']) && $sumber === 'posisi' ? 1 : 0;
            $notif = 0; // notifikasi ditangani aplikasi (ptpemaco_notif), bukan trigger

            $langkah[] = [
                'urutan' => $no,
                'tipe' => $tipe,
                'sumber' => $sumber,
                'nilai' => $nilai,
                'urutan_sumber' => $off,
                'cadangan_sumber' => $cadS,
                'cadangan_nilai' => $cadN,
                'lewati_jika_sama' => $lewati,
                'lewati_ganda' => !empty($l['lewati_ganda']) ? 1 : 0,
                'nama_hr' => $namaHr,
                'notif' => $notif,
            ];
        }
        $jumlahSign = count(array_filter($langkah, fn ($l) => $l['tipe'] === 'sign'));
        if ($jumlahSign !== 1 || end($langkah)['tipe'] !== 'sign') {
            $this->gagal('Alur harus punya tepat satu langkah tanda tangan dan itu harus langkah terakhir.');
        }

        return [[
            'nama' => $nama,
            'id_base' => implode(',', $base),
            'id_divisi' => $idDivisi,
            'keterangan' => $ket === '' ? null : $ket,
            'aktif' => $request->boolean('aktif', true) ? 1 : 0,
        ], $langkah];
    }

    /** validasi aturan tambahan SPPD: tidak ada jenjang/divisi, ada daftar tipe langkah yang dihapus, langkah tanpa aturan "satu tanda tangan" */
    private function bersihkanTambahan(Request $request, string $modul, string $nama, string $ket): array
    {
        $hapus = array_values(array_unique(array_map('strval', (array) $request->input('hapus_tipe', []))));
        foreach ($hapus as $h) {
            if (!in_array($h, self::TIPE_TAMBAHAN, true)) {
                $this->gagal('Jenis langkah yang dihapus tidak dikenal: ' . $h);
            }
        }
        $semuaBase = DB::table('base_positions')->pluck('id')->map(fn ($v) => (string) $v)->all();
        $divisiAda = DB::table('divisions')->pluck('organization_id')->map(fn ($v) => (int) $v)->all();
        $mentah = array_values((array) $request->input('langkah', []));
        if (count($mentah) < 1 || count($mentah) > 10) {
            $this->gagal('Alur harus punya 1 sampai 10 langkah.');
        }
        $langkah = [];
        foreach ($mentah as $i => $l) {
            $no = $i + 1;
            $l = (array) $l;
            $tipe = (string) ($l['tipe'] ?? '');
            if (!in_array($tipe, self::TIPE_TAMBAHAN, true)) {
                $this->gagal("Langkah $no: jenis langkah tidak valid.");
            }
            $sumber = (string) ($l['sumber'] ?? '');
            if (!in_array($sumber, self::SUMBER_TAMBAHAN, true) || ($sumber === 'pemohon_sppd' && $modul !== 'hotel30')) {
                $this->gagal("Langkah $no: sumber orang tidak valid.");
            }
            $nilai = null;
            $off = 0;
            if ($sumber === 'posisi') {
                // boleh jabatan yang sedang kosong: pengajuan akan gagal dengan pesan jelas sampai ada pemegangnya
                if (!is_numeric($l['nilai'] ?? null) || !DB::table('ptpemaco_hr_production.positions')->where('id', (int) $l['nilai'])->exists()) {
                    $this->gagal("Langkah $no: pilih jabatan.");
                }
                $nilai = (int) $l['nilai'];
            } elseif ($sumber === 'id_base') {
                if (!is_numeric($l['nilai'] ?? null) || !in_array((string) (int) $l['nilai'], $semuaBase, true)) {
                    $this->gagal("Langkah $no: pilih jenjang.");
                }
                $off = (int) ($l['urutan_sumber'] ?? 0);
                if ($off < 0 || $off > 20) {
                    $this->gagal("Langkah $no: urutan pemegang jenjang harus 1 sampai 21.");
                }
                $nilai = (int) $l['nilai'];
            } elseif ($sumber === 'puk_divisi') {
                if (!is_numeric($l['nilai'] ?? null) || !in_array((int) $l['nilai'], $divisiAda, true)) {
                    $this->gagal("Langkah $no: pilih divisi.");
                }
                $nilai = (int) $l['nilai'];
            }
            $langkah[] = [
                'urutan' => $no,
                'tipe' => $tipe,
                'sumber' => $sumber,
                'nilai' => $nilai,
                'urutan_sumber' => $off,
                'cadangan_sumber' => null,
                'cadangan_nilai' => null,
                'lewati_jika_sama' => null,
                'lewati_ganda' => !empty($l['lewati_ganda']) ? 1 : 0,
                'nama_hr' => !empty($l['nama_hr']) && $sumber === 'posisi' ? 1 : 0,
                'notif' => 0,
            ];
        }

        return [[
            'nama' => $nama,
            'id_base' => '*',
            'id_divisi' => null,
            'hapus_tipe' => $hapus ? implode(',', $hapus) : null,
            'keterangan' => $ket === '' ? null : $ket,
            'aktif' => $request->boolean('aktif', true) ? 1 : 0,
        ], $langkah];
    }

    /** @return array{0:string,1:?int,2:int} [sumber, nilai, urutan_sumber] */
    private function sumber(string $modul, $sumber, $nilai, $off, array $semuaBase, array $punyaPemegang, string $label): array
    {
        $sumber = (string) $sumber;
        if (!in_array($sumber, self::SUMBER[$modul], true)) {
            $this->gagal("$label: sumber orang tidak valid.");
        }
        if ($sumber === 'posisi') {
            if (!is_numeric($nilai) || !in_array((int) $nilai, $punyaPemegang, true)) {
                $this->gagal("$label: pilih jabatan yang ada pemegangnya.");
            }

            return [$sumber, (int) $nilai, 0];
        }
        if ($sumber === 'id_base') {
            if (!is_numeric($nilai) || !in_array((string) (int) $nilai, $semuaBase, true)) {
                $this->gagal("$label: pilih jenjang.");
            }
            if (!is_numeric($off) || (int) $off < 0 || (int) $off > 20) {
                $this->gagal("$label: urutan pemegang jenjang harus 1 sampai 21.");
            }

            return [$sumber, (int) $nilai, (int) $off];
        }

        return [$sumber, null, 0];
    }

    private function tulisLangkah(int $idAturan, array $langkah): void
    {
        $db = $this->db();
        $db->table('alur_langkah')->where('id_aturan', $idAturan)->delete();
        foreach ($langkah as $l) {
            $db->table('alur_langkah')->insert(['id_aturan' => $idAturan] + $l);
        }
    }

    public function tambah(Request $request, string $modul)
    {
        $this->cekModul($modul);
        if (in_array($modul, self::TAMBAHAN, true)) {
            $this->gagal('Aturan ini satu-satunya untuk alurnya: hanya bisa diubah, tidak bisa ditambah.');
        }
        [$aturan, $langkah] = $this->bersihkan($request, $modul, null);
        $db = $this->db();
        $db->transaction(function () use ($db, $modul, $aturan, $langkah) {
            $id = $db->table('alur_aturan')->insertGetId($aturan + [
                'modul' => $modul,
                'diubah_oleh' => Employe::employeId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->tulisLangkah($id, $langkah);
        });

        return response()->json(['status' => true, 'message' => 'Aturan alur ditambahkan', 'data' => $this->semua($modul)], 200);
    }

    public function ubah(Request $request, $id)
    {
        $db = $this->db();
        $lama = $db->table('alur_aturan')->where('id', (int) $id)->first();
        if (!$lama) {
            $this->gagal('Aturan tidak ditemukan.', 404);
        }
        [$aturan, $langkah] = $this->bersihkan($request, $lama->modul, (int) $lama->id);
        $db->transaction(function () use ($db, $lama, $aturan, $langkah) {
            $db->table('alur_aturan')->where('id', $lama->id)->update($aturan + [
                'diubah_oleh' => Employe::employeId(),
                'updated_at' => now(),
            ]);
            $this->tulisLangkah((int) $lama->id, $langkah);
        });

        return response()->json(['status' => true, 'message' => 'Aturan alur disimpan', 'data' => $this->semua($lama->modul)], 200);
    }

    public function hapus($id)
    {
        $db = $this->db();
        $lama = $db->table('alur_aturan')->where('id', (int) $id)->first();
        if (!$lama) {
            $this->gagal('Aturan tidak ditemukan.', 404);
        }
        if (in_array($lama->modul, self::TAMBAHAN, true)) {
            $this->gagal('Aturan ini satu-satunya untuk alurnya: hanya bisa diubah atau dinonaktifkan, tidak bisa dihapus.');
        }
        $db->table('alur_aturan')->where('id', $lama->id)->delete(); // langkah ikut terhapus (foreign key cascade)

        return response()->json(['status' => true, 'message' => 'Aturan alur dihapus', 'data' => $this->semua($lama->modul)], 200);
    }

    /**
     * Uji alur: jalankan bangun_alur untuk dokumen contoh di dalam transaksi lalu dibatalkan (tidak ada data tersimpan, tanpa notifikasi).
     * SPPD : employe_id (pegawai tujuan, atau '-') dan pengaju.   Surat: sign_by (penandatangan) dan id_divisi.
     */
    public function simulasi(Request $request, string $modul)
    {
        $this->cekModul($modul);
        if (in_array($modul, self::TAMBAHAN, true)) {
            return $this->simulasiTambahan($request, $modul);
        }
        $doc = 'SIMULASI-' . bin2hex(random_bytes(6));
        if ($modul === 'sppd') {
            $employe = trim((string) $request->input('employe_id'));
            $pengaju = trim((string) $request->input('pengaju'));
            if ($employe === '') {
                $this->gagal('Pilih pegawai tujuan (atau "tanpa pegawai tujuan").');
            }
            if ($employe === '-' && $pengaju === '') {
                $this->gagal('Pilih pengaju SPPD.');
            }
            $kunci = $employe === '-' ? '-' : DB::table('struktur_lengkap_oke')->where('employe_id', $employe)->value('id_base');
            $args = ['sppd', $doc, $kunci === null ? null : (string) $kunci, null, $employe === '-' ? $pengaju : $employe, null, $employe === '-' ? $pengaju : $employe, 0];
        } else {
            $ttd = trim((string) $request->input('sign_by'));
            if ($ttd === '' || !$request->filled('id_divisi')) {
                $this->gagal('Pilih penandatangan dan divisi surat.');
            }
            $kunci = DB::table('struktur_lengkap_oke')->where('employe_id', $ttd)->value('id_base');
            $args = ['surat', $doc, $kunci === null ? null : (string) $kunci, (int) $request->input('id_divisi'), null, $ttd, $ttd, 0];
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->statement('CALL ptpemaco_esign.bangun_alur(?, ?, ?, ?, ?, ?, ?, ?)', $args);
            $baris = $db->table('verif_steps')->where('id_doc', $doc)->orderBy('step')->orderBy('id')->get(['step', 'type', 'id_employe', 'id_current_position']);
        } finally {
            $db->rollBack();
        }

        return response()->json(['status' => true, 'data' => $this->rapikanLangkah($baris)], 200);
    }

    private function rapikanLangkah($baris): array
    {
        $nama = DB::table('struktur_lengkap_oke')->whereIn('employe_id', $baris->pluck('id_employe')->all())->pluck('first_name', 'employe_id');

        return $baris->map(fn ($b) => [
            'step' => (int) $b->step,
            'tipe' => $b->type,
            'nama' => $nama[$b->id_employe] ?? $b->id_employe,
            'jabatan' => $b->id_current_position,
        ])->values()->all();
    }

    /**
     * Uji alur tambahan pada SPPD yang sudah ada: bangun_alur_tambahan dijalankan di dalam transaksi lalu dibatalkan,
     * jadi langkah dokumen itu tidak berubah. Mengembalikan langkah sebelum dan sesudah.
     */
    private function simulasiTambahan(Request $request, string $modul)
    {
        $sppd = DB::connection('mysql4')->table('sppds')->where('id', (int) $request->input('id_sppd'))->first(['nomor_dokumen', 'employe_id', 'jabatan']);
        if (!$sppd) {
            $this->gagal('Pilih SPPD contoh.');
        }
        $db = $this->db();
        $ambil = fn () => $db->table('verif_steps')->where('id_doc', $sppd->nomor_dokumen)->orderBy('step')->orderBy('id')->get(['step', 'type', 'id_employe', 'id_current_position']);
        $sebelum = $ambil();
        $sesudah = collect();
        $galat = null;
        $db->beginTransaction();
        try {
            $db->statement('CALL ptpemaco_esign.bangun_alur_tambahan(?, ?, ?, ?)', [$modul, $sppd->nomor_dokumen, $sppd->employe_id, $sppd->jabatan]);
            $sesudah = $ambil();
        } catch (\Throwable $e) {
            $galat = $e->getMessage();
        } finally {
            $db->rollBack();
        }
        if ($galat !== null) {
            $pos = strpos($galat, 'Alur tambahan SPPD:');
            $this->gagal($pos !== false ? trim(substr($galat, $pos, 200)) : 'Alur gagal dibentuk untuk SPPD ini.');
        }

        return response()->json(['status' => true, 'data' => ['sebelum' => $this->rapikanLangkah($sebelum), 'sesudah' => $this->rapikanLangkah($sesudah)]], 200);
    }
}
