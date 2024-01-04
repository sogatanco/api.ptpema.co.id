<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Model;

class Porto extends Model
{
    protected $connection ='mysql2';
    protected $table = 'portofolio';
    protected $primaryKey='id_porto';
}
