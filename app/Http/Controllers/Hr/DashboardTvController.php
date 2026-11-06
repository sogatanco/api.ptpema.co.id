<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardTvController extends Controller
{
    // bagian yang tersedia di Dashboard TV, dalam urutan bawaan
    protected const SLIDES = [
        'hadir' => 'Daftar Hadir',
        'tidak' => 'Daftar Tidak Hadir',
        'kemarin' => 'Belum Clock Out Kemarin',
        'grafikHari' => 'Grafik Kehadiran Harian',
        'grafikBulan' => 'Grafik Kehadiran Bulanan',
        'rapat' => 'Daftar Rapat Hari Ini',
        'struktur' => 'Struktur Organisasi',
        'lalulintas' => 'Lalu Lintas Banda Aceh dan Sekitarnya',
    ];

    // lama tayang bawaan (detik) bagian yang muat satu layar; daftar panjang mengikuti lama gulir (null)
    protected const DURASI_BAWAAN = [
        'grafikHari' => 30,
        'grafikBulan' => 30,
        'lalulintas' => 30,
    ];

    protected const DURASI_MIN = 5;
    protected const DURASI_MAX = 600;

    protected function defaults(): array
    {
        $out = [];
        $i = 1;
        foreach (self::SLIDES as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label, 'urutan' => $i++, 'tampil' => true, 'durasi' => null, 'durasi_bawaan' => self::DURASI_BAWAAN[$key] ?? null];
        }

        return $out;
    }

    // urutan dan status tampil tiap bagian; bagian yang belum tersimpan memakai nilai bawaan
    public function slides()
    {
        $rows = collect();

        if (Schema::hasTable('dashboard_tv_slides')) {
            $rows = DB::table('dashboard_tv_slides')->get()->keyBy('slide_key');
        }

        $list = collect($this->defaults())->map(function ($d) use ($rows) {
            $r = $rows->get($d['key']);

            if (!$r) {
                return $d;
            }

            return array_merge($d, [
                'urutan' => (int) $r->urutan,
                'tampil' => (bool) $r->tampil,
                'durasi' => isset($r->durasi) && $r->durasi ? (int) $r->durasi : null,
            ]);
        })->sortBy('urutan')->values()->all();

        return new PostResource(true, 'dashboard tv slides', $list);
    }

    public function updateSlides(Request $request)
    {
        $items = $request->input('slides');

        if (!is_array($items) || count($items) === 0) {
            return new PostResource(false, 'slides wajib diisi', []);
        }

        $seen = [];
        foreach ($items as $item) {
            $key = $item['key'] ?? null;

            if (!is_string($key) || !array_key_exists($key, self::SLIDES) || isset($seen[$key])) {
                return new PostResource(false, 'bagian tidak dikenal atau ganda: ' . json_encode($key), []);
            }

            $seen[$key] = true;

            $durasi = $item['durasi'] ?? null;
            if ($durasi !== null && $durasi !== '' && (!is_numeric($durasi) || $durasi < self::DURASI_MIN || $durasi > self::DURASI_MAX)) {
                return new PostResource(false, 'durasi harus ' . self::DURASI_MIN . '-' . self::DURASI_MAX . ' detik', []);
            }
        }

        $hasDurasi = Schema::hasColumn('dashboard_tv_slides', 'durasi');

        $by = Employe::employeId();

        DB::transaction(function () use ($items, $by, $hasDurasi) {
            foreach (array_values($items) as $i => $item) {
                $row = ['urutan' => $i + 1, 'tampil' => !empty($item['tampil']) ? 1 : 0, 'updated_by' => $by, 'updated_at' => now()];

                if ($hasDurasi) {
                    $row['durasi'] = isset($item['durasi']) && $item['durasi'] !== '' ? (int) $item['durasi'] : null;
                }

                DB::table('dashboard_tv_slides')->updateOrInsert(['slide_key' => $item['key']], $row);
            }
        });

        return $this->slides();
    }
}
