<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEntity extends Model
{
    use HasFactory;
    protected $table = 'notification_entity';
    protected $fillable = [
        'entity'
    ];
}
