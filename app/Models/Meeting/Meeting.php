<?php

namespace App\Models\Meeting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;
    protected $table = 'meetings';
    protected $fillable = [
        'topic',
        'link',
        'meeting_id',
        'password',
        'start_time',
        'end_time',
        'created_by',
        'canceled_at',
    ];
}
