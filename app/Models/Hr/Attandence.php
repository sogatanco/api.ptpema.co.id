<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attandence extends Model
{
    protected $connection = 'hr';
    protected $table = 'attendances';
}
