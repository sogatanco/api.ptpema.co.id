<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEntityType extends Model
{
    use HasFactory;
    protected $table = 'notification_entity_type';
    protected $fillable = [
        'entity_id',
        'entity_type_id',
        'url'
    ];
}
