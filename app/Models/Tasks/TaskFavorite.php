<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskFavorite extends Model
{
    use HasFactory;
    protected $table = 'project_task_favorite';
    protected $fillable = [
        'employe_id',
        'task_id'
    ];
}
