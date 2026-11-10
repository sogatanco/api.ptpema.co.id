<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;

class SuratBackdate extends Model
{
    protected $connection = 'adm';
    protected $table = 'surat_backdate';
    protected $guarded = [];
}
