<?php

namespace App\Models\Pengajuan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pengajuan\ApprovedUmumPengajuan;
use App\Models\Pengajuan\SubPengajuan;

class Pengajuan extends Model
{
    use HasFactory;
    protected $connection='umumPengajuan';
    protected $table='umum_pengajuan';
    protected $guarded=['id'];

    public function approvals()
    {
        return $this->hasMany(ApprovedUmumPengajuan::class, 'pengajuan_id');
    }

    public function sub_pengajuan()
    {
        return $this->hasMany(SubPengajuan::class, 'pengajuan_id');
    }
}
