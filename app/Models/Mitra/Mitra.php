<?php

namespace App\Models\Mitra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mitra extends Model
{
    protected $connection ='mysql3';
    protected $table = 'users';
    protected $primaryKey='id_user';
}
