<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profil extends Model
{
    protected $connection = 'hr';
    protected $table = 'employee_personal_data';
}
