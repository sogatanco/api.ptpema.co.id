<?php

namespace App\Console\Commands;

use App\Models\Employe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Mengisi sppds.divisi_pengaju untuk SPPD lama dari riwayat jabatan HR (employee_histories):
// jabatan pengaju yang berlaku pada tanggal SPPD dibuat. Tanpa riwayat -> memakai divisi saat ini; tanpa keduanya dilewati.
class SppdIsiDivisiPengaju extends Command
{
    protected $signature = 'sppd:isi-divisi-pengaju {--dry-run : hanya tampilkan hasil, tidak menyimpan}';

    protected $description = 'Isi divisi pengaju SPPD lama dari riwayat jabatan HR';

    public function handle()
    {
        $hr = config('database.connections.hr.database');
        $db = DB::connection('mysql4');
        $dry = (bool) $this->option('dry-run');

        // jabatan utama saja (bukan penugasan PIC/PS), urut waktu
        $riwayat = DB::table("$hr.employee_histories")
            ->where(function ($q) {
                $q->whereNull('as_pic')->orWhere('as_pic', 0);
            })
            ->whereNotIn('contract_need', ['PIC', 'PS'])
            ->whereNotNull('position_id')
            ->orderBy('start_date')->orderBy('id')
            ->get(['employe_id', 'position_id', 'start_date'])
            ->groupBy('employe_id');
        $divisiJabatan = DB::table("$hr.positions")->pluck('division_id', 'id');

        $hasil = ['riwayat' => 0, 'saat_ini' => 0, 'dilewati' => 0];
        $db->table('sppds')->whereNull('divisi_pengaju')->orderBy('id')->each(function ($s) use ($riwayat, $divisiJabatan, $db, $dry, &$hasil) {
            $div = null;
            $rows = $riwayat->get($s->submitted_by);
            if ($rows && $rows->isNotEmpty()) {
                $tgl = substr((string) $s->created_at, 0, 10);
                // riwayat terakhir yang sudah berlaku saat SPPD dibuat; sebelum riwayat pertama -> riwayat pertama
                $pakai = $rows->filter(fn ($r) => $r->start_date <= $tgl)->last() ?? $rows->first();
                $div = $divisiJabatan[$pakai->position_id] ?? null;
                $sumber = 'riwayat';
            }
            if (!$div) {
                $div = Employe::getEmployeDivision($s->submitted_by)->organization_id ?? null;
                $sumber = 'saat_ini';
            }
            if (!$div) {
                $hasil['dilewati']++;
                return;
            }
            $hasil[$sumber]++;
            if (!$dry) {
                $db->table('sppds')->where('id', $s->id)->update(['divisi_pengaju' => $div]);
            }
        });

        $this->info(($dry ? '[dry-run] ' : '') . 'dari riwayat HR: ' . $hasil['riwayat'] . ', dari divisi saat ini: ' . $hasil['saat_ini'] . ', dilewati: ' . $hasil['dilewati']);
    }
}
