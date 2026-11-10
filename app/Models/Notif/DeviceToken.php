<?php

namespace App\Models\Notif;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $connection = 'notif';
    protected $table = 'device_tokens';
    protected $guarded = [];
    public $timestamps = false;
}
