<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Structure extends Model
{
    protected $table='struktur_lengkap_oke';

    public static function getPukDivision()
    {
        return self::join('divisions as a', 'a.PUK', '=', 'struktur_lengkap_oke.employe_id')
                   ->select('struktur_lengkap_oke.*') // Mengambil data hanya dari tabel struktur_lengkap_oke
                   ->get();
    }
}