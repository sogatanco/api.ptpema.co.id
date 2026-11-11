<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;

class SuratBackdate extends Model
{
    protected $connection = 'adm';
    protected $table = 'surat_backdate';
    protected $guarded = [];

    /** id surat yang dibuat dari permintaan backdate; kosong bila tabelnya belum dibuat */
    public static function suratIds(): array
    {
        try {
            return static::where('status', 'selesai')->whereNotNull('surat_id')->pluck('surat_id')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
