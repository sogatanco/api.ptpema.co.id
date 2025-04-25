<?php

namespace App\Models\Daily;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyAttachment extends Model
{
    use HasFactory;

    protected $table = 'daily_attachments';

    protected $fillable = [
        'daily_id',
        'file_name',
    ];
}
