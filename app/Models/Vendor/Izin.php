<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Model;

class Izin extends Model
{
    protected $connection ='mysql2';
    protected $table = 'izin_usaha';
    protected $primaryKey='id_izin';
}
