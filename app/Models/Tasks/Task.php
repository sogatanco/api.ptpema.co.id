<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use HasFactory;
    protected $table = 'project_tasks';
    protected $fillable = [
        "project_id",
        "task_id",
        "division",
        "task_parent",
        "task_title",
        "task_desc",
        "task_progress",
        "created_by"
    ];

    public static function taskProject($id){
        // $task = new Task;
        $data = DB::table('project_task_approval')
                ->leftJoin('project_tasks', 'project_tasks.task_id', "=", "project_task_approval.task_id")
                ->leftJoin('projects', 'projects.project_id', '=', 'project_tasks.project_id' )
                ->where('project_task_approval.approval_id', $id)
                ->first();  

        return $data; 
          
    }
}   

