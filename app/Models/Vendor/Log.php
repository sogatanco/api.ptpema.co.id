<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $connection ='mysql2';
    protected $table = 'activity_log';
    protected $primaryKey='id_log';
}
