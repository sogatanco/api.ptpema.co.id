<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKbli extends Model
{
    use HasFactory;
    protected $connection ='mysql2';
    protected $table = 'master_kbli';
    protected $primaryKey = 'id_kbli';
}
