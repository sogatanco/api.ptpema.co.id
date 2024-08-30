<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Projects\Project;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskApproval;
use App\Models\Tasks\TaskPic;
use App\Models\Tasks\TaskFile;
use App\Models\Tasks\TaskStatus;
use App\Models\Tasks\TaskFavorite;
use App\Models\Comment\Comment;
use App\Models\Employe;
use App\Models\Organization;
use App\Models\Structure;
use App\Models\Projects\ProjectStage;
use App\Models\Projects\ProjectHistory;
use App\Models\Projects\TaskProgress;
use App\Models\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TaskResource;
use App\Http\Requests\TaskRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class ProjectReportController extends Controller
{
    public function allProjectToExcel()
    {
        $projects = Project::leftJoin('project_stages', 'project_stages.project_id', '=', 'projects.project_id')
                    ->where('projects.division', 22)
                    ->get();

        for ($p=0; $p < count($projects); $p++) { 
            $projects[$p]['task'] = Task::where('project_id', $projects[$p]->project_id)->get();
        };

        return response()->json([
            'data' => $projects
        ], 200);

        // fase projek
        $query = 22;
        $projectId = 127;

        // fase projek
        if($query){
            $wherePhase = ['project_stages.project_id' => $projectId, 'project_stages.division' => +$query];
        }else{
            $wherePhase = ['project_stages.project_id' => $projectId, 'project_stages.status' => 1];
        }
        $fase = ProjectStage::select(
                    'projects.project_number as project_number',
                    'projects.project_name as project_name',
                    'project_stages.schema as schema', 
                    'project_phases.title as phase',
                    'organizations.organization_name as division',
                    'project_partners.name as partner'
                )   
                ->where($wherePhase)
                ->join('projects', 'projects.project_id', '=', 'project_stages.project_id')
                ->leftJoin('project_phases', 'project_phases.id', '=', 'project_stages.phase')
                ->leftJoin('organizations', 'organizations.organization_id','=', 'project_stages.division')
                ->leftJoin('project_partners', 'project_partners.id', '=', 'project_stages.partner')
                ->first();
        
        $picActive = ProjectHistory::where(['project_id' => $projectId, 'active' => 1])
                    ->first();

        $divisionActive = Employe::getEmployeDivision($picActive->employe_id);
        $taskByProject = TaskStatus::where(['project_id' => $projectId, 'division' => $query ? +$query : $divisionActive->organization_id])
                        ->get();
    
        $taskIdsTemp = [];

        for ($ti=0; $ti < count($taskByProject); $ti++) { 
            $taskIdsTemp[] = $taskByProject[$ti]->task_id;
        };
        // CHECK EMPLOYEE SEBAGAI PIC
        
        $all = [];
        if(count($taskIdsTemp) > 0){
            $tasks = TaskStatus::whereIn('task_id', $taskIdsTemp)
                    ->get();
    
            // CHECK LEVEL1,LEVEL2,LEVEL3
            $level1Ids = [];
            $parentIds = [];
    
            for ($t=0; $t < count($tasks); $t++) { 
                if($tasks[$t]->task_parent === null){
                    // USER SEBAGAI PIC LEVEL 1
                    array_push($level1Ids, $tasks[$t]->task_id);
                }else{
                    // ID PARENT LEVEL1 & LEVEL2
                    array_push($parentIds, $tasks[$t]->task_parent);
                }
            }
    
            $level2Ids = [];
            $level3Ids = [];
    
            // JIKA USER BUKAN PIC LEVEL1 CARI PARENT 
            // CARI PARENT 
            $parents = TaskStatus::whereIn('task_latest_status.task_id', $parentIds)
                        ->leftJoin('task_latest_status as level1', 'task_latest_status.task_parent', '=', 'level1.task_id')
                        ->select(
                            'task_latest_status.task_id', 
                            'level1.task_id as parent_id', 
                        )
                        ->get();
    
            for ($p=0; $p < count($parents); $p++) { 
                if($parents[$p]->parent_id === null && !in_array($parents[$p]->task_id, $level1Ids)){
                    // PARENT SEBAGAI LEVEL 1
                    array_push($level1Ids, $parents[$p]->task_id);
                }else if($parents[$p]->parent_id !== null){
                    // PARENT SEBAGAI LEVEL 1/2
                    array_push($level2Ids, $parents[$p]->task_id);
                    array_push($level1Ids, $parents[$p]->parent_id);
                }
            }
            
            for ($t2=0; $t2 < count($tasks); $t2++) { 
                if(in_array($tasks[$t2]->task_parent, $level1Ids)){
                    array_push($level2Ids, $tasks[$t2]->task_id);
                }
            }
    
            for ($t3=0; $t3 < count($tasks); $t3++) { 
                if(in_array($tasks[$t3]->task_parent, $level2Ids)){
                    array_push($level3Ids, $tasks[$t3]->task_id);
                }
            }
            // CHECK LEVEL1,LEVEL2,LEVEL3
    
            $allTask = array_merge($level1Ids, $level2Ids, $level3Ids);
    
            $all = TaskStatus::whereIn('task_id', $allTask)
                    ->get();
        }

        if(count($all) > 0) {
            for ($at=0; $at < count($all); $at++) { 
                $all[$at]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                    ->where('task_id', $all[$at]->task_id)
                                    ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                    ->get();
            }
        }
    
        $level1 = [];
        $level2 = [];
        $level3 = [];
    
        for ($tk=0; $tk < count($all); $tk++) { 
            if(in_array($all[$tk]->task_id, $level1Ids)){
                array_push($level1, $all[$tk]);
            }elseif(in_array($all[$tk]->task_id, $level2Ids)){
                array_push($level2, $all[$tk]);
            }else{
                array_push($level3, $all[$tk]);
            }
        }
    
        if(count($level2) > 0 ){
            // ADD LEVEL 3 TO LEVEL 2
            for ($l2=0; $l2 < count($level2); $l2++) {
                if(count($level3) > 0){
                    $lev3 = [];
                    for ($l3=0; $l3 < count($level3); $l3++) { 
                        if($level2[$l2]->task_id === $level3[$l3]->task_parent){
                            $lev3[] = $level3[$l3];
                        }
                     }
        
                    $level2[$l2]['level_3'] = $lev3;
                }
           }
           // ADD LEVEL 3 TO LEVEL 2
    
           // ADD LEVEL 2 TO LEVEL 1
           for ($l1=0; $l1 < count($level1); $l1++) { 
                $lev2 = [];
                for ($l2s=0; $l2s < count($level2); $l2s++) { 
                        if($level1[$l1]->task_id === $level2[$l2s]->task_parent){
                            $lev2[] = $level2[$l2s];
                        }
                }
    
                $level1[$l1]['level_2'] = $lev2;
           }
           // ADD LEVEL 2 TO LEVEL 1
        }

        return response()->json([
            "total" => count($level1),
            "project" => $fase,
            "data" => $level1,
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
