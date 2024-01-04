<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BidangUsaha extends Model
{
    use HasFactory;
    protected $connection ='mysql2';
    protected $table = 'bidang_usaha';
    protected $primaryKey = 'id_bidangusaha';
    protected $fillable = ['master_bidangusaha_id', 'perusahaan_id'];
}
