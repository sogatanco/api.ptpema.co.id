<?php

namespace App\Modules\Projects\Repositories;

use App\Models\Projects\Project;
use Illuminate\Support\Facades\DB;

class ProjectRepository
{
    public function listProjectByDivision($divisionId): array
    {
        try {
            $filterYear = '2025';

            $projects = Project::with(['activeStage', 'project_task'])
                ->leftJoin('project_tasks', 'projects.project_id', '=', 'project_tasks.project_id')
                ->select('projects.*', DB::raw('MAX(project_tasks.updated_at) as last_task_update'))
                ->whereExists(function ($query) use ($filterYear) {
                    $query->select(DB::raw(1))
                        ->from('project_stages')
                        ->whereColumn('project_stages.project_id', 'projects.project_id')
                        ->whereYear('project_stages.end_date', $filterYear)
                        ->where('project_stages.status', 1);
                })
                ->where('projects.division', $divisionId)
                ->groupBy('projects.project_id')
                ->orderByDesc('last_task_update')
                ->get();

            // Hitung progress per project
            $projectsWithProgress = $projects->map(function ($project) {
                $mainTasks = $project->project_task->where('task_parent', null);

                if ($mainTasks->count() > 0) {
                    $averageProgress = round($mainTasks->avg('task_progress'), 2); // 2 angka desimal
                } else {
                    $averageProgress = 0;
                }

                $project->progress_percent = $averageProgress;
                return $project;
            })->toArray();

            return $projectsWithProgress;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
