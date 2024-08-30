<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogPengajuan extends Model
{
    protected $connection = 'mysql4';
    protected $table='logs_pengajuan';
}
