<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;

class SuratVoid extends Model
{
    protected $connection = 'adm';
    protected $table = 'surat_void';
    protected $guarded = [];

    /** id surat yang di-void; kosong bila tabelnya belum dibuat (skrip surat_void.sql belum dijalankan) sehingga fitur lain tetap jalan */
    public static function ids(): array
    {
        try {
            return static::pluck('surat_id')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
