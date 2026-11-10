<?php

namespace App\Models\Notif;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $connection = 'notif';
    protected $table = 'push_subscriptions';
    protected $guarded = [];
    public $timestamps = false;
}
