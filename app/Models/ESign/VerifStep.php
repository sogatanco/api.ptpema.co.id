<?php

namespace App\Models\ESign;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifStep extends Model
{
    protected $connection = 'esign';
    protected $table = 'verif_steps';
}
