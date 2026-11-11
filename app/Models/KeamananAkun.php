<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeamananAkun extends Model
{
    protected $table = 'keamanan_akun';
    protected $primaryKey = 'employe_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = ['password_diubah_at' => 'datetime', 'pengingat_tampil_at' => 'datetime'];
}
