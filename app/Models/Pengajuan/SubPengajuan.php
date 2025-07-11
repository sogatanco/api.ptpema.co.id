<?php

namespace App\Models\Pengajuan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pengajuan\PengajuanTax;

class SubPengajuan extends Model
{
    use HasFactory;

    protected $connection = 'umumPengajuan';
    protected $table = 'sub_umum_pegajuan';
    protected $guarded = [];

    public function taxes()
    {
        return $this->hasMany(PengajuanTax::class, 'sub_pengajuan_id');
    }

}
