<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StructureMaster extends Model
{
    use HasFactory;
    protected $table = 'struktur';
    protected $fillable = [
        'position_id',
        'direct_supervisor',
    ];
}
