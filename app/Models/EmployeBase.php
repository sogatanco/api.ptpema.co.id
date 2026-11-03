<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Tabel apam `employees` (penulisan): kaitan user_id, public_key, dan baris yang dirujuk FK.
// Pembacaan data karyawan (nama, jabatan, status) memakai App\Models\Employe -> view employees_hr (sumber HR).
class EmployeBase extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    protected $table = 'employees';
    protected $primaryKey = 'employe_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'employe_id' => 'string',
    ];
    protected $fillable = [
        'employe_id',
        'public_key',
        'user_id',
        'position_id',
        'first_name',
        'last_name',
        'gender',
        'religion',
        'birthday',
        'birthday_place',
        'marital_status',
        'img',
    ];
}
