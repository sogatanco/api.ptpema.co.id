<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Verifikasi extends Model
{
    protected $connection ='mysql2';
    protected $table = 'verifikasi_perusahaan';
    protected $primaryKey='id_verifikasi';
}
