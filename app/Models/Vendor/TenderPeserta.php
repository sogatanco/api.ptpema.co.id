<?php

namespace App\Models\Vendor;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderPeserta extends Model
{
    protected $connection ='mysql2';
    protected $table = 'tender_peserta';
    protected $primaryKey='id_peserta';
    protected $fillable = [
        'perusahaan_id',
        'tender_id',
    ];

}
