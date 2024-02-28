<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectHistory;
use App\Models\Projects\ProjectPartner;
use App\Models\Projects\ActivityBase;
use App\Models\Projects\ActivityLevel;
use App\Models\Projects\BusinessPlan;
use App\Models\Projects\ProjectStage;
use App\Models\Projects\TaskProgress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Position;
use App\Models\Employe;
use App\Models\Organization;
use App\Models\Notification ;
use App\Models\Structure;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskStatus;
use App\Models\Tasks\TaskApproval;
use App\Models\Tasks\TaskPic;
use App\Models\Tasks\TaskFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
// testing
class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $employeId = Employe::employeId();
        $employeDivision = Employe::getEmployeDivision($employeId);

        $divisions = Organization::where('board_id', $employeDivision->board_id)
                    ->get();

        $divisionIds = [];
        if(count($divisions) > 0){
            for ($d=0; $d < count($divisions); $d++) { 
                array_push($divisionIds, $divisions[$d]->organization_id);
            }
        }

        $projects = Project::whereIn('division', $divisionIds)
            ->leftJoin('organizations', 'organizations.organization_id', '=', 'projects.division')
            ->leftJoin('activity_levels', 'activity_levels.level_id', '=', 'projects.level_id')
            ->orderBy('project_id', 'desc')
            ->get();

        // cari progress project
        if(count($projects) > 0){
            for ($p=0; $p < count($projects); $p++) { 

                // cari pic project active
                $data[$p]['pic_active'] = ProjectHistory::select('employees.first_name', 'positions.position_id', 'organizations.organization_id')
                        ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                        ->join('positions', 'positions.position_id', '=', 'employees.position_id')
                        ->join('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                        ->where(['project_id' => $projects[$p]->project_id, 'active' => 1])
                        ->first();

                // cari semua task parent berdasarkan divisi yg akses
                $allTask[$p] = Task::select('task_id', 'task_progress')
                        ->where(['project_id' =>$projects[$p]->project_id, 'task_parent' => null, 'division' => $data[$p]['pic_active']->organization_id])
                        ->get();

                $taskIds[$p]= [];
                $totalProgress[$p] = [];

                // inisiasi taskid dan progress value
                for ($tp=0; $tp < count($allTask[$p]); $tp++) { 
                    $taskIds[$p][] = $allTask[$p][$tp]->task_id;
                    $totalProgress[$p][] = $allTask[$p][$tp]->task_progress;
                }

                // cari task
                $taskList[$p] = TaskApproval::whereIn('task_id', $taskIds[$p])
                            ->groupBy('task_id')
                            ->orderBy('approval_id', 'desc')
                            ->get(['task_id', TaskApproval::raw('MAX(approval_id) as approval_id')]);

                // ambil status task
                $progress[$p] = array_sum($totalProgress[$p]);
                $totalTask[$p] = count($allTask[$p]);

                // cari stage aktif
                if($projects[$p]->category === 'business'){
                    // jika category business
                    $projects[$p]['current_stage'] = ProjectStage::select('project_stages.*', 'project_phases.title AS phase')
                                                    ->where(['project_id' => $projects[$p]->project_id, 'status' => 1])
                                                    ->join('project_phases', 'project_phases.id','=','project_stages.phase')
                                                    ->first();
                }else{
                    // jika category non-business
                    $projects[$p]['current_stage'] = ProjectStage::select(
                                                'project_stages.id AS stage_id',
                                                'start_date',
                                                'end_date',
                                            )
                                            ->where(['project_id' => $projects[$p]->project_id, 'status' => 1])
                                            ->first();
                }   

                $projects[$p]['total_progress'] = 0;
                
                if($progress[$p] > 0 && $totalTask > 0){
                    $projects[$p]['total_progress'] = $progress[$p]/$totalTask[$p];
                }
            }
        }

        $total = $projects->count();

        return response()->json([
            "status" => true,
            "total" => $total,
            "data" => $projects,
            'divisions' => $divisions,
            "testest" => "Coba development mode"
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "project_number" => ["required", "max:100"],
            "division" => ["required"],
            "project_name" => ["required", "max:255"],
            "start_date" => ["required"],
            "end_date" => ["required"],
            "goals" => ["required"],
            "base_id" => ["required"],
            "level_id" => ["required"],
        ]);

        if($validator->fails()){
            // throw to errors exceptions
            throw new HttpResponseException(response([
                "errors" => [
                    "message" => $validator->errors()
                ]
            ], 400));
        }

        $projectNumberIsExist = Project::where("project_number", $request->project_number)->first();
        
        if($projectNumberIsExist){
            throw new HttpResponseException(response([
                "errors" => "Project number already exist.",
            ], 409));
        }

        $user = auth()->user();
        $employeId = Employe::employeId();

        // project pic
        if(in_array('Manager', $user->roles)){
            $pic_id = $employeId;
        }else{
            // kalau staff
            // cari atasan level 1
            $structure = Structure::select('direct_atasan')
                    ->where('employe_id', $employeId)
                    ->first();

            $pic_id = $structure->direct_atasan;
        }

        $projectData = [
            'project_number' => $request->project_number,
            'division' => $request->division,
            'project_name' => ucwords($request->project_name),
            'goals' => $request->goals,
            'estimated_income' => $request->estimated_income,
            'estimated_cost' => $request->estimated_cost,
            'base_id' => $request->base_id,
            'level_id' => $request->level_id,
            'business_id' => $request->business_id,
            'category' => $request->category,
            'created_by' => $employeId
        ];
        
        $newProject = Project::create($projectData);
        
        // simpan stage
        $dataStage = [
            "project_id" => $newProject['id'],
            "phase" => $request->category === 'business' ? 1 : null,
            'division' => $newProject['division'],
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'desc' => $request->desc,
            'partner' => $request->partner,
            'status' => 1,
        ];

        $newStage = new ProjectStage($dataStage);
        $newStage->save();

        // simpan history
        $dataHistory = [
            "project_id" => $newProject['id'],
            "employe_id" => $pic_id,
            "history_desc" => "Project created",
            "active" => 1
        ];
        
        $history = new ProjectHistory($dataHistory);

        $history->save();

        return response()->json([
            "message" => "New project has been created.",
            "data" => [
                "project" => $newProject,
                "history" => $dataHistory
            ]
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show($projectId)
    {
        // $isEmployeeAccess = Employe::employeId();
        // $isEmployeeAccessDivision = Employe::getEmployeDivision($isEmployeeAccess);

        $data = Project::join(
                    'employees AS a', 
                    'a.employe_id', '=', 'projects.created_by', 
                )
                ->select(
                    'projects.*', 
                    'activity_levels.level_desc',
                    'activity_bases.base_description', 
                    'a.first_name as created_by',
                    'bp.business_desc'
                )
                ->where('projects.project_id', $projectId)
                ->leftJoin('activity_levels', 'activity_levels.level_id', '=', 'projects.level_id')
                ->leftJoin('activity_bases', 'activity_bases.base_id', '=', 'projects.base_id')
                ->leftJoin('business_plans as bp', 'bp.business_id', "=", 'projects.business_id')
                ->first();
        
        // fase saat ini 
        if($data->category === 'business'){
            // jika category business
            $data['current_stage'] = ProjectStage::select(
                                        'project_stages.id AS stage_id',
                                        'project_phases.id AS phase_id',
                                        'project_phases.title AS phase',
                                        'desc',
                                        'start_date',
                                        'end_date',
                                        'schema',
                                        'partner AS partner_name',
                                    )
                                    ->where(['project_id' => $projectId, 'status' => 1])
                                    ->leftJoin('project_phases', 'project_phases.id', '=', 'project_stages.phase')
                                    ->first();
        }else{
            // jika category non-business
            $data['current_stage'] = ProjectStage::select(
                                        'project_stages.id AS stage_id',
                                        'start_date',
                                        'end_date',
                                    )
                                    ->where(['project_id' => $projectId, 'status' => 1])
                                    ->first();
        }
        

        // cari pic project active
        $data['pic_active'] = ProjectHistory::select('employees.first_name', 'positions.position_id', 'positions.position_name', 'organizations.organization_id', 'organizations.organization_name')
                    ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                    ->join('positions', 'positions.position_id', '=', 'employees.position_id')
                    ->join('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                    ->where(['project_id' => $projectId, 'active' => 1])
                    ->first();

        // cari semua task parent berdasarkan divisi yg akses
        $allTask = Task::select('task_id', 'task_progress')
                    ->where(['project_id' => $projectId, 'task_parent' => null, 'division' => $data['pic_active']->organization_id])
                    ->get();

        $taskIds = [];
        $totalProgress = [];

        // inisiasi taskid dan progress value
        for ($tp=0; $tp < count($allTask); $tp++) { 
            $taskIds[] = $allTask[$tp]->task_id;
            $totalProgress[] = $allTask[$tp]->task_progress;
        }

        // cari task
        $taskList = TaskApproval::whereIn('task_id', $taskIds)
                        ->groupBy('task_id')
                        ->orderBy('approval_id', 'desc')
                        ->get(['task_id', TaskApproval::raw('MAX(approval_id) as approval_id')]);

        // ambil status task
        $data->task_by_active_user = [];
        $data->total_progress = 0;
        
        if(count($taskList) > 0){
            for ($tl=0; $tl < count($taskList); $tl++) { 
                $taskList[$tl] = TaskApproval::select('task_id','status')
                                ->where('approval_id', $taskList[$tl]->approval_id)
                                ->first();
            }
    
            $data->task_by_active_user = $taskList;
    
            $data->total_progress = array_sum($totalProgress)/count($allTask);
        }

        return response()->json([
            "status" => true,
            "data" => $data,
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $projectId)
    {
        // data project
        $project = [
            'project_number' => $request->project_number,
            'project_name' =>ucwords( $request->project_name),
            'goals' => $request->goals,
            'estimated_income' => $request->estimated_income,
            'estimated_cost' => $request->estimated_cost,
            'base_id' => $request->base_id,
            'level_id' => $request->level_id,
            'business_id' => $request->business_id,
            'category' => $request->category
        ];

        $projectUpdated = Project::where('project_id', $projectId)->update($project);

        if($projectUpdated){
            // data stage
            $stage = [
                'desc' => $request->desc,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'partner' => $request->partner
            ];

            $stageUpdated = ProjectStage::where('id', $request->stage_id)->update($stage);

            if($stageUpdated){
                return response()->json([
                    "message" => "Project has been updated.",
                ],200);
            } else{
                throw new HttpResponseException(response([
                    "errors" => "Something went wrong."
                ], 500));
            }

        }else{
            throw new HttpResponseException(response([
                "errors" => "Something went wrong."
            ], 500));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($projectId)
    {
        $deleted = Project::where('project_id', $projectId)->delete();

        if($deleted){
            return response()->json([
                "status" => true,
                'message' => "Project has been deleted."
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "errors" => "Something went wrong."
            ], 500));
        }
    }

    public function businessOptions()
    {
        $activityLevel = ActivityLevel::select('level_id', 'level_desc')
                        ->get();

        $activityBase = ActivityBase::select('base_id', 'base_description')
                        ->get();

        $businessPlan = BusinessPlan::select('business_id as value', 'business_desc as label')
                        ->orderBy('business_desc', 'ASC')
                        ->get();

        return response()->json([
            "status" => true,
            "activity_level" => $activityLevel,
            "activity_base" => $activityBase,
            "business_plan" => $businessPlan,
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function history($projectId)
    {
        $data = Project::select(
                'projects.project_name', 
                'project_histories.history_id', 
                'project_histories.history_desc', 
                'project_histories.created_at', 
                'project_histories.updated_at', 
                'project_histories.status', 
                'project_histories.active', 
                'employees.first_name',
                'employees.img',
                'organizations.organization_id',
                'organizations.organization_name'
                )
                ->join('project_histories', 'project_histories.project_id', '=', 'projects.project_id')
                ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                ->leftJoin('positions', 'employees.position_id', '=', 'positions.position_id')
                ->leftJoin('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                ->where(['projects.project_id' => $projectId])
                ->orderBy('history_id', 'desc')
                ->get();

        return response()->json([
            "status" => true,
            "data" => $data
        ],200, [], JSON_NUMERIC_CHECK);
    }

    public function members($projectId)
    {
        $members = TaskPic::select('employe_id')
                    ->where('project_id', $projectId)
                    ->get();

        $totalData = [];
        $membersFiltered = [];
        for ($m=0; $m < count($members) ; $m++) { 
            $array[$m] = $members[$m]->employe_id;

            if(!in_array($members[$m]->employe_id, $membersFiltered)){
                array_push($membersFiltered, $array[$m]);
            }

            array_push($totalData, $array[$m]);
        }

        $totalTaskByMember = array_count_values($totalData);

        $mem = Employe::select('employees.employe_id', 'employees.first_name', 'positions.position_name', 'organizations.organization_name')
                ->whereIn('employe_id', $membersFiltered)
                ->join('positions', "positions.position_id", "=", 'employees.position_id')
                ->join('organizations', "organizations.organization_id", "=", 'positions.organization_id')
                ->get();
        
        return response()->json([
            "total" => count($membersFiltered),
            "data" => $mem,
            "count_task" => $totalTaskByMember
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function getSuperior($employeId)
    {
        $staffDivision = Employe::getEmployeDivision($employeId);
        $staffOrganization = $staffDivision->organization_name;
        $organizationArray = explode(" ", $staffOrganization);

            // inisiasi manajer
            $manajerValue = array(0 => "Manajer");
            
            // replace divisi jadi manajer
            $managerDivision = implode(" ", array_replace($organizationArray, $manajerValue));
                 // cari id jabatan by manajer
            $position = Position::select('position_id')
                            ->where('position_name', $managerDivision)
                            ->first();
            
            // cari si manajer
            $manager = Employe::select('employe_id', 'first_name')
                        ->where('position_id', $position->position_id)
                        ->first();

            return $manager;
    }

    public function files($projectId)
    {
        $tasks = Task::where('project_id', $projectId)
                ->get();

        $filteredFiles = [];
        for ($i=0; $i < count($tasks); $i++) { 
            $files[$i] = TaskFile::select('file_id',  'task_id', 'file_name', 'created_at')
                        ->where('task_id', $tasks[$i]->task_id)
                        ->orderBy('file_id', 'desc')
                        ->get();

            if(count($files[$i]) > 0){
                for ($f=0; $f < count($files[$i]); $f++) { 
                    array_push($filteredFiles, $files[$i][$f]);
                }
            }
        }

        return response()->json([
            "status" => true,
            "data" => $filteredFiles
        ],200, [], JSON_NUMERIC_CHECK);
    }

    public function bastReview($projectId, $directorId)
    {
        $data = ProjectHistory::select(
                     'project_histories.*', 
                     'employees.first_name as new_pic',
                     'employees.employe_id as new_pic_id',
                     'positions.position_name as new_pic_position',
                     'organizations.organization_name as new_pic_division',
                )
                ->where(['project_id' => $projectId, 'status' => 'review', 'review_by' => $directorId])
                ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                ->join('positions', 'positions.position_id', '=', 'employees.position_id')
                ->join('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                ->first();

        if($data){
            // cari divisi lama active dengan status done
            $activeBy = ProjectHistory::where(['project_id' => $projectId, 'active' => 1, 'status' => 'done'])
                        ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                        ->join('positions', 'positions.position_id', '=', 'employees.position_id')
                        ->join('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                        ->first(); 

            $data->old_pic = $activeBy->first_name;
            $data->old_pic_id = $activeBy->employe_id;
            $data->old_pic_position = $activeBy->position_name;
            $data->old_pic_division = $activeBy->organization_name;
        }

        return response()->json([
            "status" => true,
            "data" => $data,
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // public function handOver(Request $request)
    // {
    //     $employeId = Employe::employeId();

    //     if($request->hasFile('file')){
    //         $file = $request->file('file');
        
    //         $thefile = $file->getClientOriginalName();
    //         $savedFile  = Storage::disk("public_project")->put('', $file);

    //         // $savedFile = $file->storeAs('project', $thefile, 'public');
    //     };

    //     $data = [
    //         'project_id' => $request->project_id,
    //         'employe_id' => $request->new_pic,
    //         'history_desc' => 'Project handover',
    //         'active' => 0,
    //         'file' => $savedFile
    //     ];

    //     $newHistory = new ProjectHistory($data);
    //     $historySaved = $newHistory->save();

    //     if($historySaved){
    //         $NotifData = [
    //             'from_employe' => $employeId,
    //             'to_employe' => $request->new_pic,
    //             'project_id' => $request->project_id,
    //             'title' => 'Project',
    //             'desc' => 'Menyerahkan projek ke Anda',
    //             'category' => 'project',
    //         ];

    //         $newNotification = new Notification($NotifData);
    //         $newNotification->save();

    //         return response()->json([
    //             "status" => true,
    //             "data" => $newHistory
    //         ], 200, [], JSON_NUMERIC_CHECK);
    //     } else {
    //         throw new HttpResponseException(response([
    //             "errors" => "Something went wrong."
    //         ], 500));
    //     }
    // }

    public function bastApproval(Request $request, $historyId)
    {
        $data = [
            'history_desc' => $request->status === 'revision' ? 'BAST review' : 'Project handover', 
            'review_by' => $request->review_by,
            'comment' => $request->note,
            'status' => $request->status
        ];

        $updated = ProjectHistory::where('history_id', $historyId)
                    ->update($data);

        if($updated){

            $NotifData = [
                'from_employe' => $request->status === 'revision' ? $request->review_by : $request->old_pic,
                'to_employe' => $request->notif_to,
                'project_id' => $request->project_id,
                'title' => $request->status === 'revision' ? 'BAST Review' : 'Project Handover',
                'desc' => $request->status === 'revision' ? 'Merevisi BAST Anda' : 'Meminta konfirmasi handover projek',
                'category' => $request->status === 'revision' ? 'bast' : 'project',
            ];

            $newNotification = new Notification($NotifData);
            $newNotification->save();

            return response()->json([
                "status" => true,
            ], 200, [], JSON_NUMERIC_CHECK);
        } else {
            throw new HttpResponseException(response([
                "errors" => "Something went wrong."
            ], 500));
        }

    }

    public function handOver(Request $request)
    {
        $employeId = Employe::employeId();

        if($request->hasFile('file')){
            $file = $request->file('file');
            $thefile = $file[0]->getClientOriginalName();
            $savedFile  = Storage::disk("public_project")->put($thefile, file_get_contents($file[0]));
        };

        $structure = Structure::select('direct_atasan')
                    ->where('employe_id', $employeId)
                    ->first();

        $data = [
            'project_id' => $request->project_id,
            'employe_id' => $request->new_pic,
            'history_desc' => 'BAST Review',
            'active' => 0,
            'file' => $thefile,
            'status' => 'review',
            'review_by' => $structure->direct_atasan
        ];

        $newHistory = new ProjectHistory($data);
        $historySaved = $newHistory->save();

        if($historySaved){

            // updated history yg lama
            $whereHistory = ['project_id' => $request->project_id, 'employe_id' => $employeId, 'active' => 1];
            $historyUpdate = ProjectHistory::where($whereHistory)
                            ->update(['status' => 'done']);

            // ambil data fase yang aktif
            $currentStage = ProjectStage::where(['project_id' => $request->project_id, 'status' => 1])
                            ->first();

            // jika fase aktif adalah planning
            if($currentStage->phase == 2) {
                
                // jika fase aktif sebelumnya belum ada data partner
                if($currentStage->partner === null){
                    $updateStageData = [
                        'partner' => $request->partner,
                        'schema' => $request->schema,
                    ];
                }else{
                    $updateStageData = [
                        'schema' => $request->schema,
                    ];
                }

                // update skema pada fase planning
                ProjectStage::where('id', $currentStage['id'])
                            ->update($updateStageData);
            }

            // simpan notifikasi
            $NotifData = [
                'from_employe' => $employeId,
                'to_employe' => $structure->direct_atasan,
                'project_id' => $request->project_id,
                'title' => 'Project Handover',
                'desc' => 'Meminta persetujuan BAST projek',
                'category' => 'project',
            ];

            $newNotification = new Notification($NotifData);
            $newNotification->save();

            return response()->json([
                "status" => true,
                "data" => $newHistory,
                "currentStage" => $currentStage
            ], 200, [], JSON_NUMERIC_CHECK);
        } else {
            throw new HttpResponseException(response([
                "errors" => "Something went wrong."
            ], 500));
        }
    }

    public function getHandover($employeId, $projectId)
    {
        $employeDivision = Employe::getEmployeDivision($employeId);
        $data = ProjectHistory::where(
                    [
                        'project_histories.project_id' => $projectId, 
                        'project_histories.employe_id' => $employeId, 
                        'active' => 0, 
                        'status' => 'handover'
                    ]
                )
                ->select('project_histories.*', 'employees.first_name')
                ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                ->first();

        if($data){

            // fase yang aktif
            // $data['current_stage'] = ProjectStage::select(
            //                             'project_stages.*',
            //                             'project_phases.title AS phase_name',
            //                             'project_partners.name AS partner',
            //                         )
            //                         ->where(['project_id' => $projectId, 'status' => 1])
            //                         ->join('project_phases', 'project_phases.id', '=', 'project_stages.phase')
            //                         ->join('project_partners', 'project_partners.id', '=', 'project_stages.partner')
            //                         ->first();

            $data['current_stage'] = ProjectStage::select(
                                        'project_stages.*',
                                    )
                                    ->where(['project_id' => $projectId, 'status' => 1])
                                    ->first();

            // cari pic active sebelumnya
            $projectHistoryActive = ProjectHistory::select('project_histories.employe_id','employees.first_name')
                                ->where([
                                        'project_id' => $projectId,
                                        'active' => 1,
                                        'status' => 'done'
                                    ])
                                ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                                ->first();

            $oldDivision = Employe::getEmployeDivision($projectHistoryActive->employe_id);

            $data->old_pic = $projectHistoryActive->first_name;
            $data->from_division = $oldDivision->organization_name;
            $data->to_division = $employeDivision->organization_name;
        }

        return response()->json([
            "data" => $data,
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function handoverConfirm(Request $request, $historyId)
    {   
        //  validasi stage baru
        $validator = Validator::make($request->all(), [
            "desc_stage" => ["required"],
            "start_date" => ["required"],
            "end_date" => ["required"],
        ]);

        if($validator->fails()){
            // throw to errors exceptions
            throw new HttpResponseException(response([
                "message" => "column cannot be empty." 
            ], 400));
        }

        $updated = ProjectHistory::where('history_id', $historyId)->update(['active' => 1]);

        if($updated){

            // history project
            $projectHistory = ProjectHistory::select('project_id', 'employe_id')
                            ->where('history_id', $historyId)
                            ->first();

            // nonaktifkan pic lama berdasarkan id project
            ProjectHistory::where(['project_id' => $projectHistory->project_id, 'active' => 1, 'status' => 'done'])
                        ->update(['active' => 0]); 

            // data stage sebelumnya
            $oldStage = ProjectStage::where(['project_id' => $projectHistory->project_id, 'status' => 1])
                        ->first();

            // nonaktifkan stage lama
            $oldStageUpdate = ProjectStage::where('id', $oldStage->id)
                                ->update(['status' => 0]);

            // divisi pic yang baru
            $employeDivision = Structure::select('organization_id')
                            ->where('employe_id', $projectHistory->employe_id)
                            ->first();

            // insert stage baru
            $stageData = [
                'project_id' => $projectHistory->project_id,
                'phase' => $oldStage->phase + 1,
                'division' => $employeDivision->organization_id,
                'desc' => $request->desc_stage,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => 1,
                'schema' => $oldStage->schema,
                'partner' => $oldStage->partner
            ];

            $newStage = new ProjectStage($stageData);
            $newStage->save();

            return response()->json([
                "status" => true,
                "message" => 'Project has been accepted.',
            ], 200, [], JSON_NUMERIC_CHECK);
        }else{
            throw new HttpResponseException(response([
                "error" => "Confirmation failed."
            ], 500));
        }
    }

    // OLD FUNCTION projectByEmployeDivision
    public function projectByEmployeDivision($employeId)
    {
        $user = auth()->user();
        $empId = Employe::employeId();

        if($employeId !== $empId){
            throw new HttpResponseException(response([
                "message" => 'Unauthorized.' 
            ], 401));
        }

        $employeDivision = Structure::select('organization_id')
                            ->where('employe_id', $employeId)
                            ->first();

        $projectByStage = ProjectStage::select('project_stages.*')
                            ->where(['division' => $employeDivision->organization_id])
                            ->get();

        // project id array
        $projectIds = [];
        for ($pi=0; $pi < count($projectByStage); $pi++) { 
            $projectIds[] = $projectByStage[$pi]->project_id;
        };

        $projects = Project::whereIn('project_id', $projectIds)
                    ->leftJoin('organizations', 'organizations.organization_id', '=', 'projects.division')
                    ->leftJoin('activity_levels', 'activity_levels.level_id', '=', 'projects.level_id')
                    ->orderBy('project_id', 'desc')
                    ->get();

        if(count($projects) > 0){
                for ($p=0; $p < count($projects); $p++) { 
                    
                    // data dari stage yang aktif
                    if($projects[$p]->category === 'business'){
                        $projects[$p]['current_stage'] = ProjectStage::select('project_stages.*', 'project_phases.title AS phase')
                                                    ->where(['project_id' => $projects[$p]->project_id, 'status' => 1])
                                                    ->join('project_phases', 'project_phases.id', '=', 'project_stages.phase')
                                                    ->first();
                    }else{
                        $projects[$p]['current_stage'] = ProjectStage::where(['project_id' => $projects[$p]->project_id, 'status' => 1])
                                                    ->first();
                    }   

                    // cari pic project active
                    $data[$p]['pic_active'] = ProjectHistory::select('employees.first_name', 'positions.position_id', 'organizations.organization_id')
                            ->join('employees', 'employees.employe_id', '=', 'project_histories.employe_id')
                            ->join('positions', 'positions.position_id', '=', 'employees.position_id')
                            ->join('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                            ->where(['project_id' => $projects[$p]->project_id, 'active' => 1])
                            ->first();

                    // cari semua task parent berdasarkan divisi yg akses
                    $allTask[$p] = Task::select('task_id', 'task_progress')
                            ->where(['project_id' =>$projects[$p]->project_id, 'task_parent' => null, 'division' => $data[$p]['pic_active']->organization_id])
                            ->get();
    
                    $taskIds[$p]= [];
                    $totalProgress[$p] = [];
    
                    // inisiasi taskid dan progress value
                    for ($tp=0; $tp < count($allTask[$p]); $tp++) { 
                        $taskIds[$p][] = $allTask[$p][$tp]->task_id;
                        $totalProgress[$p][] = $allTask[$p][$tp]->task_progress;
                    }
    
                    // cari task
                    $taskList[$p] = TaskApproval::whereIn('task_id', $taskIds[$p])
                                ->groupBy('task_id')
                                ->orderBy('approval_id', 'desc')
                                ->get(['task_id', TaskApproval::raw('MAX(approval_id) as approval_id')]);
    
                    // ambil status task
                    $progress[$p] = array_sum($totalProgress[$p]);
                    $totalTask[$p] = count($allTask[$p]);
    
                    $projects[$p]['total_progress'] = 0;
                    
                    if($progress[$p] > 0 && $totalTask > 0){
                        $projects[$p]['total_progress'] = $progress[$p]/$totalTask[$p];
                    }
                }
        }

        return response()->json([
            "message" => true,
            "total" => count($projects),
            "data" => $projects
        ],200, [], JSON_NUMERIC_CHECK);
    }
    // OLD FUNCTION projectByEmployeDivision

    public function totalDataByEmploye($employeId)
    {
        $user = auth()->user();

        // jika direksi
        if(in_array("Director", $user->roles)){
            $totalProject = Project::count();

            // task yang parent aja
            $totalTask = Task::where('task_parent', null)
                        ->count();

            $totalInProgress = TaskStatus::where('status', 1)
                                ->count();

            $totalDone = TaskStatus::where('status', 3)
                        ->count();
        }else{
            $employeDivision = Structure::select('organization_id')
                            ->where('employe_id', $employeId)
                            ->first();

            $totalProject = Project::where('division', $employeDivision->organization_id)
                            ->count();

            $totalTask = TaskStatus::where('employe_id', $employeId)
                        ->count();

            $totalInProgress = TaskStatus::where(['employe_id' => $employeId, 'status' => 1])
                        ->count();

            $totalDone = TaskStatus::where(['employe_id' => $employeId, 'status' => 3])
                        ->count();
        }

        return response()->json([
            "status" => true,
            "data" => [
                'total_project' => $totalProject,
                'total_task' => $totalTask,
                'total_inprogress' => $totalInProgress,
                'total_done' => $totalDone,
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function partnerOptions()
    {
        $data = ProjectPartner::select('id', 'name')->get(); 
        
        return response()->json([
            "status" => true,
            "data" => $data
        ], 200);
    } 

    public function timelineData()
    {
        $employeId = Employe::employeId();

        // ambil semua projek
        $projects = Project::select('project_id', 'project_name', 'organizations.organization_name')
                ->join('organizations', 'organizations.organization_id', '=', 'projects.division')
                ->orderBy('project_id', 'desc')
                ->get();

        $list = [];
        for ($i=0; $i < count($projects); $i++) { 
            // cari task by project dan employe
            $where = ['project_task_pics.project_id' => $projects[$i]->project_id, 'project_task_pics.employe_id' => $employeId];
            $projects[$i]['tasks'] = TaskPic::select('project_task_pics.task_id','task_parent', 'task_title', 'start_date', 'end_date', 'status')
                                    ->where($where)
                                    ->join('task_latest_status', 'task_latest_status.task_id', '=', 'project_task_pics.task_id')
                                    ->get();

            $tasks[$i] = [];

            for ($t=0; $t < count($projects[$i]['tasks']); $t++) { 

                $bgColor[$t] = '';

                if($projects[$i]['tasks'][$t]->status === 0){
                    $bgColor[$t] = 'rgb(21, 137, 252)';
                }elseif($projects[$i]['tasks'][$t]->status === 1){
                    $bgColor[$t] = 'rgb(238,157,35)';
                }else{
                    $bgColor[$t] = 'rgb(14,183,175)';
                }
                

                $tasks[$i][$t] = [
                    "id" => $projects[$i]['tasks'][$t]->task_id,
                    "start_date" => $projects[$i]['tasks'][$t]->start_date,
                    "end_date" => $projects[$i]['tasks'][$t]->end_date,
                    "title" => $projects[$i]['tasks'][$t]->task_title,
                    "status" => $projects[$i]['tasks'][$t]->status,
                    "occupancy" => 3600,
                    "subtitle" => "",
                    "description" => "",
                    "bgColor" => $bgColor[$t]
                ];
            }

            $list[$i] = [
                "id" => $projects[$i]->project_id,
                "label" => [
                    "title" => $projects[$i]->project_name,
                    "subtitle" => $projects[$i]->organization_name
                ],
                "task" => count($projects[$i]['tasks']),
                "data" => $tasks[$i]
            ];

        }

        return response()->json([
            "data" => $list
        ], 200);
    }

    public function createActivityBase(Request $request)
    {   
        if($request->activity_name){
            $new = new ActivityBase(['base_description' => $request->activity_name]);
            $saved = $new->save();

            if($saved){
                return response()->json([
                    "status" => true,
                    "message" => "Activity base has been added"
                ], 200);
            }
        }else{
            throw new HttpResponseException(response([
                "error" => "Field cannot be empty" 
            ], 400));
        }
    }

    public function progressCollection(Request $request)
    {
        // PROGRESS PROJEK ITU BY STAGES [Status = 1] INGAT!!

        // ids = project id array
        // $ids = json_decode($request->ids);
        $ids = $request->ids;

        // hitung total prograss setiap project
        if(count($ids) > 0){

            $projects = Project::select('project_id')
                    ->whereIn('project_id', $ids)
                    ->get();

            // kumpulin task parent
            $allTask = TaskStatus::whereIn('project_id', $ids)
                    ->where('task_parent', null)
                    ->select('task_id')
                    ->get();

            $parentIds = [];
            for ($at=0; $at < count($allTask); $at++) { 
                array_push($parentIds, $allTask[$at]->task_id);
            }

            // ambil progres task parent
            $progressTask = TaskProgress::whereIn('task_id', $parentIds)
                            ->get();

            for ($p=0; $p < count($projects); $p++) { 
                $proj = [];
                for ($pt=0; $pt < count($progressTask); $pt++) { 
                    if($projects[$p]->project_id === $progressTask[$pt]->project_id){
                        $proj[] = $progressTask[$pt]->progress;
                    }
                }
                $projects[$p]['progress'] = count($proj) > 0 ? array_sum($proj)/count($proj) : 0;
            }
        }

        return response()->json([
            "status" => true,
            "data" => $projects
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function assignedProject()
    {
        // employe sebagai manager
        $employeId = Employe::employeId();
        // key: projectId, stages active,

        $employeDivision = Employe::getEmployeDivision($employeId);

        $projects = TaskPic::where('project_task_pics.employe_id', $employeId)
                    ->join('projects', 'project_task_pics.project_id', '=', 'projects.project_id')
                    ->where('projects.division', '!=', $employeDivision->organization_id)
                    ->get();

        return response()->json([
            "status" => true,
            "total" => count($projects),
            "data" => $projects
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
