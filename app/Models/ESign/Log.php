<?php

namespace App\Models\ESign;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $connection = 'esign';
    protected $table='logs';
}
