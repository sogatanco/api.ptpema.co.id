<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

// Jabatan menurut HR (sumber id jabatan: struktur_lengkap_oke.position_id sekarang = id ini)
class Position extends Model
{
    protected $connection = 'hr';
    protected $table = 'positions';
}
