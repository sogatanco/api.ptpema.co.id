<?php

namespace App\Models\Layar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layar extends Model
{
    protected $connection = 'layar';
    protected $table = 'images';
    protected $fillable = [
        'url',
        'name',
        'duration',
    ];
}
