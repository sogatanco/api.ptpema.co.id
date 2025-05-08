<?php

namespace App\Models\Pengajuan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Employe;

class ApprovedUmumPengajuan extends Model
{
    use HasFactory;
    protected $connection='umumPengajuan';
    protected $table='approved_umum_pengajuan';
    protected $guarded=['id'];

    public function employe()
    {
        return $this->belongsTo(Employe::class, 'employe_id', 'employe_id');
    }

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return $this->employe
            ? $this->employe->first_name . ' ' . $this->employe->last_name
            : null;
    }

}
