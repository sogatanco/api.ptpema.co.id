<?php

namespace App\Models\Inven;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetChild extends Model
{
    use HasFactory;
    protected $fillable = ['asset_number', 'id_parent', 'responsible'];
}
