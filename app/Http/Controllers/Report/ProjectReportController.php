<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Projects\Project;
use App\Models\Tasks\TaskStatus;


class ProjectReportController extends Controller
{
    public function allProjectToExcel()
    {
        $projects = Project::leftJoin('project_stages', 'project_stages.project_id', '=', 'projects.project_id')
                    ->where('projects.division', 22)
                    ->get();

        for ($p=0; $p < count($projects); $p++) { 
            $projects[$p]['task'] = TaskStatus::where('project_id', $projects[$p]->project_id)
                                ->where('task_parent', NULL)
                                ->get();
        };

        return response()->json([
            'data' => $projects
        ], 200);
    }
}
