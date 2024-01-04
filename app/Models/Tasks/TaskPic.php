<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskPic extends Model
{
    use HasFactory;
    protected $table = 'project_task_pics';
    protected $fillable = [
        'project_id',
        'employe_id',
        'task_id',
    ];
}
