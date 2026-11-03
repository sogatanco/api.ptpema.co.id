<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

// Public key e-sign karyawan di HR (db/hr_sync/23_hr_public_keys_apply.sql). Private key tetap berkas terenkripsi di storage apam.
class EmployeePublicKey extends Model
{
    protected $connection = 'hr';
    protected $table = 'employee_public_keys';
    protected $primaryKey = 'employe_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['employe_id', 'public_key'];
}
