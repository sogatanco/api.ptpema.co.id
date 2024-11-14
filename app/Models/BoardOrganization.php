<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoardOrganization extends Model
{
    use HasFactory;
    protected $table = 'board_organizations';
    protected $fillable = [
        'board_code',
        'board_name',
    ];
}
