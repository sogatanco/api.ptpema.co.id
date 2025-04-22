<?php

namespace App\Models\Daily;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Daily extends Model
{
    use HasFactory;

    protected $table = 'daily';

    protected $fillable = [
        'employe_id',
        'task_id',
        'activity_name',
        'progress',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];
}
