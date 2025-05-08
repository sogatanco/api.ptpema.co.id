<?php

namespace App\Models\Pengajuan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubPengajuan extends Model
{
    use HasFactory;

    protected $connection = 'umumPengajuan';
    protected $table = 'sub_umum_pegajuan';
    protected $guarded = [];
}
