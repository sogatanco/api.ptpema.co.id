<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Perusahaan extends Model
{
    use HasFactory;
    protected $connection ='mysql2';
    protected $table = "perusahaan";    
    protected $guarded = [];
    
}
