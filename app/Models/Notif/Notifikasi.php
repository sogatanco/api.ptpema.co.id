<?php

namespace App\Models\Notif;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $connection = 'notif';
    protected $table = 'notifications';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['data' => 'array'];
}
