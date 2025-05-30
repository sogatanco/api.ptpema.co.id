<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;
    protected $table = "positions";
    protected $primaryKey = 'position_id';
    protected $fillable = [
        'organization_id',
        'id_base',
        "position_code",
        "position_name"
    ];
}
