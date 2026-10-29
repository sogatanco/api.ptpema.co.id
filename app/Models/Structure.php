<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Structure extends Model
{
    protected $table = 'struktur_lengkap_oke';

    // Satu orang bisa menjadi PUK lebih dari satu divisi (divisinya sendiri + divisi yang dia PS/PIC-kan),
    // jadi hasilnya satu baris per divisi. puk_organization_id adalah divisi yang dipimpin PUK itu;
    // organization_id tetap divisi asli pegawainya.
    public static function getPukDivision()
    {
        return self::join('divisions as a', 'a.PUK', '=', 'struktur_lengkap_oke.employe_id')
            ->select('struktur_lengkap_oke.*', 'a.organization_id as puk_organization_id', 'a.organization_name as puk_organization_name')
            ->orderBy('a.organization_id')
            ->get();
    }
    public static function getPukDivisionUnderMe($employe_id)
    {
        return self::join('divisions as a', 'a.PUK', '=', 'struktur_lengkap_oke.employe_id')
            ->where('direct_atasan', $employe_id)
            ->select('struktur_lengkap_oke.*', 'a.organization_id as puk_organization_id', 'a.organization_name as puk_organization_name')
            ->orderBy('a.organization_id')
            ->get();
    }
}