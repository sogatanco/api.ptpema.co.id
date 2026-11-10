<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;

class SuratTtdBasah extends Model
{
    protected $connection = 'adm';
    protected $table = 'surat_ttd_basah';
    protected $guarded = [];

    /** [surat_id => ada file ber-ttd?]; kosong bila tabelnya belum dibuat (skrip surat_ttd_basah.sql belum dijalankan) */
    public static function peta(): array
    {
        try {
            return static::get(['surat_id', 'file_ttd'])->mapWithKeys(fn ($r) => [(int) $r->surat_id => !empty($r->file_ttd)])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
