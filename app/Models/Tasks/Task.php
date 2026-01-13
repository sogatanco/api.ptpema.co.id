<?php

namespace App\Models\Tasks;

use App\Models\Daily\Daily;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectStage;
use App\Models\Tasks\TaskPic;
use App\Models\Tasks\TaskApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use HasFactory;
    protected $table = 'project_tasks';
    protected $primaryKey = 'task_id';
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

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function daily()
    {
        return $this->hasMany(Daily::class, 'task_id', 'task_id');
    }

    public function pics()
    {
        return $this->hasMany(TaskPic::class, 'task_id');
    }

    public function approval()
    {
        return $this->hasOne(TaskApproval::class, 'task_id', 'task_id')->latestOfMany('approval_id');
    }

    public function parent()
    {
        return $this->belongsTo(Task::class, 'task_parent', 'task_id');
    }

    public function scopeLevelTwo($query)
    {
        return $query
            ->whereNotNull('task_parent')
            ->whereHas('parent', function ($q) {
                $q->whereNull('task_parent');
            });
    }
}
