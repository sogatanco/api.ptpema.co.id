<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskFile extends Model
{
    use HasFactory;
    protected $table = 'project_task_files';
    protected $fillable = [
        'task_id',
        'file_name',
    ];
}
