<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Model;

class SppdVoid extends Model
{
    protected $connection = 'mysql4';
    protected $table = 'sppd_void';
    protected $guarded = [];

    /** [sppd_id => alasan]; kosong bila tabelnya belum dibuat (skrip sppd_void.sql belum dijalankan) sehingga fitur lain tetap jalan */
    public static function peta(): array
    {
        try {
            return static::pluck('alasan', 'sppd_id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function ids(): array
    {
        return array_map('intval', array_keys(static::peta()));
    }
}
