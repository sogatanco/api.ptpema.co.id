<?php
namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    protected $connection ='mysql2';
    protected $table = 'tender';
    protected $primaryKey='id_tender';
}