<?php

namespace App\Models\Pengajuan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanTax extends Model
{
    use HasFactory;
    protected $connection='umumPengajuan';
    protected $table='pengajuan_taxs';
    protected $guarded=['id'];

}
