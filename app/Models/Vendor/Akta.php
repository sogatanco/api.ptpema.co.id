<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Model;

class Akta extends Model
{
    protected $connection ='mysql2';
    protected $table = 'akta';
    protected $primaryKey='id_akta';
}

