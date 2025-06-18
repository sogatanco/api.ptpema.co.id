<?php

namespace App\Models\Daily;

use App\Models\Tasks\Task;
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
        'category',
        'progress',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }
}
