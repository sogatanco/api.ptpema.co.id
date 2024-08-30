<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Projects\Project;

class ProjectReportController extends Controller
{
    public function allProjectToExcel()
    {
        $projects = Project::select('project_number', 'project_name', 'goals')
                    ->where('division', 22)
                    ->get();

        return response()->json([
            'data' => $projects
        ], 200);
    }
}
