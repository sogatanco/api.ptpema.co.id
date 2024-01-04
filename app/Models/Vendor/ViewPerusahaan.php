<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewPerusahaan extends Model
{
    protected $connection ='mysql2';
    protected $table = "view_perusahaans";
}
