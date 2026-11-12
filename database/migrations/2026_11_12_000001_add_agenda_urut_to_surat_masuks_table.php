<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'adm';

    public function up(): void
    {
        Schema::connection('adm')->table('surat_masuks', function (Blueprint $table) {
            if (!Schema::connection('adm')->hasColumn('surat_masuks', 'agenda_urut')) {
                $table->unsignedSmallInteger('agenda_tahun')->nullable();
                $table->unsignedInteger('agenda_urut')->nullable();
            }
        });

        // isi surat yang sudah ada dengan nomor yang sama seperti hitungan lama (urut id per tahun), lalu dikunci permanen
        $urut = [];
        foreach (DB::connection('adm')->table('surat_masuks')->orderBy('id')->get(['id', 'created_at']) as $row) {
            $tahun = (int) date('Y', strtotime($row->created_at ?: 'now'));
            $urut[$tahun] = ($urut[$tahun] ?? 0) + 1;
            DB::connection('adm')->table('surat_masuks')->where('id', $row->id)->update(['agenda_tahun' => $tahun, 'agenda_urut' => $urut[$tahun]]);
        }

        Schema::connection('adm')->table('surat_masuks', function (Blueprint $table) {
            $table->unique(['agenda_tahun', 'agenda_urut'], 'surat_masuks_agenda_unik');
        });
    }

    public function down(): void
    {
        Schema::connection('adm')->table('surat_masuks', function (Blueprint $table) {
            $table->dropUnique('surat_masuks_agenda_unik');
            $table->dropColumn(['agenda_tahun', 'agenda_urut']);
        });
    }
};
