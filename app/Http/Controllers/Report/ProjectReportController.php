<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Projects\Project;

class ProjectReportController extends Controller
{
    public function allProjectToExcel()
    {
        $projects = Project::select(
                    'project_number', 
                    'project_name', 
                    'goals',
                    'project_stages.desc',
                    'project_stages.start_date',
                    'project_stages.end_date',
                    )
                    ->leftJoin('project_stages', 'project_stages.project_id', '=', 'projects.project_id')
                    ->with('project_tasks')
                    ->where('projects.division', 22)
                    ->get();

        return response()->json([
            'data' => $projects
        ], 200);
    }
}
