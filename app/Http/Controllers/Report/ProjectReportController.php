<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Projects\Project;

class ProjectReportController extends Controller
{
    public function allProjectToExcel()
    {
        $projects = Project::leftJoin('project_stages', 'project_stages.project_id', '=', 'projects.project_id')
                    ->where('projects.division', 22)
                    ->get();

        return response()->json([
            'data' => $projects
        ], 200);
    }
}
