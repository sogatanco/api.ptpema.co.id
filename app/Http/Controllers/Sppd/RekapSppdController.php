<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Models\Sppd\ListSppdRingan;
use App\Models\Sppd\SppdVoid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rekap semua SPPD yang sudah di-approve (tanda tangan akhir selesai), semua kategori dan divisi,
 * dipisah antara yang aktif dan yang void. Tahun mengikuti tanggal berangkat (cadangan: tanggal dibuat); urutan terbaru berdasarkan created_at.
 */
class RekapSppdController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['tahun' => 'nullable|string|max:4']);
        $tahun = (string) $request->query('tahun', 'all');
        if ($tahun !== 'all' && !preg_match('/^\d{4}$/', $tahun)) {
            return response()->json(['status' => false, 'message' => 'Tahun tidak valid.'], 422);
        }

        $void = SppdVoid::peta();
        $semua = ListSppdRingan::where('current_status', 'signed')
            ->get(['id', 'nomor_sppd', 'nomor_dokumen', 'employe_id', 'nama', 'jabatan', 'created_at', 'mulai_tugas', 'selesai_tugas', 'realisasi_status', 'dasar_ketetapan'])
            ->map(function ($s) {
                $s->acuan = $s->mulai_tugas ?: $s->created_at;
                $s->tahun = $s->acuan ? (string) date('Y', strtotime($s->acuan)) : null;

                return $s;
            });

        $tahunTersedia = $semua->pluck('tahun')->filter()->unique()->sort()->reverse()->values();
        $baris = $tahun === 'all' ? $semua : $semua->where('tahun', $tahun);
        $baris = $baris->sortByDesc(fn ($s) => sprintf('%s-%012d', $s->created_at, $s->id))->values();
        $ids = $baris->pluck('id')->all();

        $db = DB::connection('mysql4');
        // tujuan dan kategori per SPPD
        $tujuan = $db->table('tujuan_sppds as t')->leftJoin('categori_sppds as c', 'c.id', '=', 't.kategori')
            ->whereIn('t.id_sppd', $ids)->orderBy('t.waktu_berangkat')->get(['t.id_sppd', 't.detail_tujuan', 'c.categori_sppd'])->groupBy('id_sppd');
        // perkiraan biaya: jumlah total_biaya semua tujuan
        $biaya = $db->table('hitungan_biaya')->whereIn('id_sppd', $ids)->selectRaw('id_sppd, SUM(total_biaya) AS total')->groupBy('id_sppd')->pluck('total', 'id_sppd');
        // biaya realisasi (rill): jumlah semua rincian realisasi; kosong bila realisasi belum diisi (sama dengan list_sppd.rill_biaya)
        $rill = $db->table('realisasi_biaya')->whereIn('id_sppd', $ids)->selectRaw('id_sppd, SUM(jumlah_rill) AS total')->groupBy('id_sppd')->pluck('total', 'id_sppd');
        // divisi pegawai (non-karyawan: employe_id '-' tidak punya divisi)
        $divisi = DB::table('struktur_lengkap_oke')->whereIn('employe_id', $baris->pluck('employe_id')->filter()->unique()->all())
            ->pluck('organization_name', 'employe_id');

        $rekap = $baris->map(function ($s) use ($tujuan, $biaya, $rill, $divisi, $void) {
            $t = $tujuan->get($s->id, collect());
            $alasan = $void[$s->id] ?? null;

            return [
                'id' => (int) $s->id,
                'nomor_sppd' => $s->nomor_sppd,
                'nama' => $s->nama,
                'jabatan' => $s->jabatan,
                'divisi' => $divisi[$s->employe_id] ?? null,
                'tujuan' => $t->pluck('detail_tujuan')->filter()->unique()->values()->all(),
                'kategori' => $t->pluck('categori_sppd')->filter()->unique()->values()->all(),
                'mulai' => $s->mulai_tugas,
                'selesai' => $s->selesai_tugas,
                'dibuat' => (string) $s->created_at,
                'perkiraan_biaya' => isset($biaya[$s->id]) ? (float) $biaya[$s->id] : null,
                'realisasi_biaya' => isset($rill[$s->id]) ? (float) $rill[$s->id] : null,
                'realisasi_status' => $s->realisasi_status,
                'void' => $alasan !== null,
                'alasan_void' => $alasan,
            ];
        });

        $aktif = $rekap->where('void', false)->values();
        $batal = $rekap->where('void', true)->values();

        return response()->json(['status' => true, 'data' => [
            'tahun' => $tahun,
            'tahun_tersedia' => $tahunTersedia,
            'aktif' => $aktif,
            'void' => $batal,
            'ringkasan' => [
                'jumlah_aktif' => $aktif->count(),
                'jumlah_void' => $batal->count(),
                'total_biaya_aktif' => (float) $aktif->sum('perkiraan_biaya'),
                'total_realisasi_aktif' => (float) $aktif->sum('realisasi_biaya'),
                'jumlah_realisasi_aktif' => $aktif->whereNotNull('realisasi_biaya')->count(),
            ],
        ]], 200);
    }
}
