<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
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
use App\Models\Projects\Project;
use App\Models\Projects\ProjectStage;
use App\Models\Projects\ProjectHistory;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TaskResource;
use App\Http\Requests\TaskRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            "msg" => "form tasks endpoint"
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TaskRequest $request)
    {
        $data = $request->validated();

        if($request->hasFile('files')){
            $files = $request->file('files');
        
            $thefile = $files[0]->getClientOriginalName();
            $savedFile  = Storage::disk("public_taskfiles")->put('', $files[0]);
        }

        $employeId = Employe::employeId();
        $employeDivision = Employe::getEmployeDivision($employeId);

        // save new task
        $data['division'] = $employeDivision->organization_id;
        $data['created_by'] = $employeId;
        $newTask = new Task($data);
        $newTaskSaved = $newTask->save();

        if($newTaskSaved){
            
            $task = Task::where('task_id', $newTask->id)
                ->first();

            if($task->task_parent !== null){
                // siapa parentnya
                $parent = $task->task_parent;

                // jumlah subtask berapa berdasarkan parent
                $subtaskSum = Task::where('task_parent', $parent)
                            ->get();

                // jumlahin semua progress
                $totalProgress = 0;
                $totalSubtask = count($subtaskSum);

                for ($sum=0; $sum < $totalSubtask; $sum++) { 
                    $totalProgress = $subtaskSum[$sum]->task_progress + $totalProgress;
                }

                // total progress dibagi jumlah subtask
                $totalPercentage = $totalProgress/$totalSubtask;

                // update ke parent
                $parentData = [
                    'task_progress' => $totalPercentage,
                ];

                Task::where('task_id', $parent)->update($parentData);
            }
        }
        
        // save filename
        if(isset($savedFile)){
            $fileData = [
                'task_id' => $newTask->id,
                'file_name' => $savedFile
            ];
            $newFile = new TaskFile($fileData);
            $newFile->save();
        }

        // save task approval
        for ($i=0; $i < count($data['task_pic']); $i++) { 

            // simpan ke table pic
            $dataPics[$i] = [
                'project_id' => $data['project_id'],
                'employe_id' => $data['task_pic'][$i]['value'],
                'task_id' => $newTask->id
            ];  

            $newTaskPic = new TaskPic($dataPics[$i]);
            $newTaskPic->save();
        }

        $dataApproval = [
            'task_id' => $newTask->id,
            'employe_id' => $data['task_pic'][0]['value'],
            'status' => 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date']
        ];

        $newTaskApproval = new TaskApproval($dataApproval);
        $newTaskApproval->save();

        $data = Task::taskProject($newTaskApproval->id);

        return new TaskResource($data);

    }

    /**
     * Display the specified resource.
     */
    public function show($projectId, $taskId)
    {
        $where = ['project_id' => $projectId, 'task_id' => $taskId];
        $task = Task::where($where)->first();

        return response()->json([
            "status" => true,
            "data" => $task
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $taskId)
    {

        $data = [
            'task_title' => $request->task_title, 
            'task_desc' => $request->task_desc, 
            'task_progress' => $request->task_progress,
        ];

        $isUpdated = Task::where('task_id', $taskId)
                    ->update($data);

        if($isUpdated){

            TaskApproval::where('approval_id', $request->approval_id)
                        ->update(['start_date' => $request->start_date, 'end_date' => $request->end_date]);

            $task = Task::where('task_id', $taskId)
                    ->first();

            if($task->task_parent !== null){
                // siapa parentnya
                $parent = $task->task_parent;

                // jumlah subtask berapa berdasarkan parent
                $subtaskSum = Task::where('task_parent', $parent)
                            ->get();

                // jumlahin semua progress
                $totalProgress = 0;
                $totalSubtask = count($subtaskSum);

                for ($sum=0; $sum < $totalSubtask; $sum++) { 
                    $totalProgress = $subtaskSum[$sum]->task_progress + $totalProgress;
                }

                // total progress dibagi jumlah subtask
                $totalPercentage = $totalProgress/$totalSubtask; 

                // update ke parent
                $parentData = [
                    'task_progress' => $totalPercentage,
                ];

                Task::where('task_id', $parent)->update($parentData);
            }

            return response()->json([
                "status" => true,
                "data" => $task,
                "appid" => $request->approval_id,
                "endDate" => $request->end_date
            ], 200, [], JSON_NUMERIC_CHECK);
        } else {
            throw new HttpResponseException(response([
                "errors" => [
                    "message" => "Update task failed."
                ]
            ], 400));
        }

        // return new TaskResource($taskUpdated);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($taskId)
    {
        $deletedParent = Task::where('task_id', $taskId)->delete();

        $checkChild = Task::where('task_parent', $taskId)->get();
        if(!empty($checkChild)){
            $deletedChild = Task::where('task_parent', $taskId)->delete();
        }

        if($deletedParent){
            return response()->json([
                "status" => true,
                "message" => "Task has been deleted."
            ],200, [], JSON_NUMERIC_CHECK);
        }else{
            throw new HttpResponseException(response([
                "errors" => [
                    "message" => "Delete task failed."
                ]
            ], 400));
        }
    }

    public function taskByEmploye($projectId)
    {
        $employeId = Employe::employeId();

        // cari divisi aktif
        $employeDivision = ProjectHistory::select('employe_id')
                        ->where(['project_id' => $projectId, 'active' => 1])
                        ->first();

        if($employeId !== $employeDivision->employe_id){
            $employeCompare = Structure::select('organization_id')
                            ->whereIn('employe_id', [$employeDivision->employe_id, $employeId])
                            ->get();

            $isMemberActive = $employeCompare[0]->organization_id === $employeCompare[1]->organization_id;
        } else {
            // jika user active adalah manager
            $isMemberActive = true;
        }

        $listTask = [];

        // if($isMemberActive){
        // }

        // cari dulu dari table pic
        $taskByPic = TaskPic::where(['project_id' => $projectId, 'employe_id' => $employeId])
                    ->get();

        $taskIds = [];
        for ($ti=0; $ti < count($taskByPic); $ti++) { 
            $taskIds[] = $taskByPic[$ti]->task_id;
        };

        $tasks = TaskStatus::select('task_id', 'task_parent')
                ->whereIn('task_id', $taskIds)
                ->get();

        $parentTasks =[];
        $parentSubtasks=[];
        for ($p=0; $p < count($tasks); $p++) { 
            if($tasks[$p]->task_parent === null){
                $parentTasks[] = $tasks[$p]->task_id;
            }else{
                if(!in_array($tasks[$p]->task_parent, $parentTasks)){
                    $parentSubtasks[] = $tasks[$p]->task_parent;
                }
            }
        }

        // jika parent cari subtask
        $pic_parent =[];
        if(count($parentTasks) > 0){
            $pic_parent = TaskStatus::whereIn('task_id', $parentTasks)
                            ->get();

            for ($pp=0; $pp < count($pic_parent); $pp++) { 
                $pic_parent[$pp]['comments'] = Comment::where('task_id', $pic_parent[$pp]->task_id)->count();
                
                $pic_parent[$pp]['files'] = TaskFile::select('file_id', 'file_name')
                                                ->where('task_id', $pic_parent[$pp]->task_id)
                                                ->get(); 

                $pic_parent[$pp]['subtasks'] = TaskStatus::where([
                                                    'task_parent' => $pic_parent[$pp]->task_id,
                                                ])
                                                ->select('task_latest_status.*', TaskStatus::raw('(SELECT COUNT(*) FROM comments WHERE comments.task_id = task_latest_status.task_id) AS comments'))
                                                ->get() ;

                $pic_parent[$pp]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                            ->where('task_id', $pic_parent[$pp]->task_id)
                                            ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                            ->get();
                                                
                for ($stf1=0; $stf1 < count($pic_parent[$pp]['subtasks']); $stf1++) { 
                    $pic_parent[$pp]['subtasks'][$stf1]['files'] = TaskFile::select('file_id', 'file_name')
                                                                    ->where('task_id', $pic_parent[$pp]['subtasks'][$stf1]->task_id)
                                                                    ->get();

                    $pic_parent[$pp]['subtasks'][$stf1]['pics'] =  TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                                                    ->where('task_id', $pic_parent[$pp]['subtasks'][$stf1]->task_id)
                                                                    ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                                                    ->get();
                }                                   
            }
        }

        // jika subtask cari parent
        $pic_subtask =[];
        if(count($parentSubtasks) > 0){
        $pic_subtask= TaskStatus::whereIn('task_id', $parentSubtasks)
                    ->get();

        for ($ps=0; $ps < count($pic_subtask); $ps++) { 
            $pic_subtask[$ps]['comments']= Comment::where('task_id', $pic_subtask[$ps]->task_id)->count();
            
            $pic_subtask[$ps]['files'] = TaskFile::select('file_id', 'file_name')
                                            ->where('task_id', $pic_subtask[$ps]->task_id)
                                            ->get(); 
            
            $pic_subtask[$ps]['subtasks'] = TaskStatus::where([
                                                'task_parent' => $pic_subtask[$ps]->task_id,
                                                'project_task_pics.employe_id' => $employeId
                                            ])
                                            ->select(
                                                'task_latest_status.*', 
                                                TaskStatus::raw('(SELECT COUNT(*) FROM comments WHERE comments.task_id = task_latest_status.task_id) AS comments'),
                                                'project_task_pics.employe_id as orangnya'
                                            )
                                            ->join('project_task_pics', 'project_task_pics.task_id', '=', 'task_latest_status.task_id')
                                            ->get() ;
            
            $pic_subtask[$ps]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                        ->where('task_id', $pic_subtask[$ps]->task_id)
                                        ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                        ->get();
                                            
            for ($stf=0; $stf < count($pic_subtask[$ps]['subtasks']); $stf++) { 
                $pic_subtask[$ps]['subtasks'][$stf]['files'] = TaskFile::select('file_id', 'file_name')
                                                                ->where('task_id', $pic_subtask[$ps]['subtasks'][$stf]->task_id)
                                                                ->get();

                $pic_subtask[$ps]['subtasks'][$stf]['pics'] =  TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                                                ->where('task_id', $pic_subtask[$ps]['subtasks'][$stf]->task_id)
                                                                ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                                                ->get();
            }    
        }
        }

        if(count($pic_parent) > 0 && count($pic_subtask) > 0){
            $listTask = array_merge($pic_parent->toArray(), $pic_subtask->toArray());
        }elseif(count($pic_parent) > 0){
            $listTask = $pic_parent;
        }else{
            $listTask = $pic_subtask;
        }

        return response()->json([
            "status" => true,
            "total" => count($listTask),
            "is_member_active" => $isMemberActive,
            'tasks' => $listTask,
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function getTodo($projectId)
    {
        // manfaatin table view aja!

        $employeId = Employe::employeId();
        
        $whereParent = ['project_id' => $projectId, 'task_parent' => null];
        $parent = Task::select(
                    'task_id',
                    'task_title',
                    'task_desc',
                    'task_progress',
                    'project_tasks.created_at',
                    'employees.first_name as created_by'
                )
                ->where($whereParent)->orderBy('project_tasks.task_id', 'desc')
                ->join('employees', 'employees.employe_id', '=', 'project_tasks.created_by')
                ->get();
        $count = count($parent);

        // parent with status || get by employee
        for ($p=0; $p < $count; $p++) { 

            $whereHistory = ['task_id' => $parent[$p]->task_id, 'employe_id' => $employeId];
            $lastHistory[$p] = TaskApproval::select('employe_id','status', 'start_date', 'end_date')
                        ->orderBy('approval_id', 'desc')
                        ->where($whereHistory)
                        ->first();

            if($lastHistory[$p] !== null){
                $parent[$p]->pic = $lastHistory[$p]->employe_id;
                $parent[$p]->status = $lastHistory[$p]->status;
                $parent[$p]->start_date = (!empty($lastHistory[$p]->start_date)) ? $lastHistory[$p]->start_date : null;
                $parent[$p]->end_date =  $lastHistory[$p]->end_date;  

                // $parent[$p]->pics = TaskPic::select('pic_id', 'first_name', 'project_task_pics.employe_id', 'progress', 'file')
                //                     ->join('employees', 'employees.employe_id', '=', 'project_task_pics.employe_id')
                //                     ->where('task_id', $parent[$p]->task_id)
                //                     ->get();
                
                // pics
                $parent[$p]->pics = TaskApproval::select('project_task_approval.employe_id', 'first_name', 'progress', 'file')
                                ->where('task_id', $parent[$p]->task_id)
                                ->join('employees', 'employees.employe_id', '=', 'project_task_approval.employe_id')
                                ->get();
                
                // comments
                $parent[$p]->comments = Comment::where('task_id', $parent[$p]->task_id)->count();

                // file task
                $parent[$p]->files = TaskFile::select('file_id', 'file_name')
                                ->where('task_id', $parent[$p]->task_id)
                                ->get();
                // cari subtask
                $whereSubtask = ['project_id' => $projectId, 'task_parent' => $parent[$p]->task_id, 'employe_id' => $employeId];
                $subtask[$p] = Task::where($whereSubtask)
                            ->join('project_task_approval', 'project_task_approval.task_id', '=', 'project_tasks.task_id')
                            ->get();

                            
                $parent[$p]->subtasks = $subtask[$p];

                // pics subtask
                for ($sp=0; $sp < count($subtask[$p]); $sp++) { 
                    $subtaskIds[] = $subtask[$p][$sp]->task_id;

                    $parent[$p]->subtasks[$p]->pics = TaskApproval::select('project_task_approval.employe_id', 'first_name', 'progress', 'file')
                                                    ->where('task_id', $subtask[$p][$sp]->task_id)
                                                    ->join('employees', 'employees.employe_id', '=', 'project_task_approval.employe_id')
                                                    ->get();
                }
                
            }
        };

        // $allTaskParent = Task::where($whereParent)->get();
        // $count = count($allTaskParent);

        // $task = [];
        // $taskFiltered = [];
        // for ($p=0; $p < $count; $p++) { 
        //     $where = ['task_id' => $allTaskParent[$p]->task_id, 'status' => $status];
        //     $task[$p] = TaskApproval::where($where)
        //                 ->orderBy('approval_id', 'desc')
        //                 ->first();

        //     if($task[$p] !== null){
        //         array_push($taskFiltered, $task[$p]);
        //     }
        // };

        // parent tasks
        // $tasks = Task::where($whereParent)
        //         ->get();

        // // $filteredTask = [];
        // for ($i=0; $i < count($tasks); $i++) { 
        //     $list[$i] = TaskApproval::where('project_task_approval.task_id', $tasks[$i]->task_id)
        //                         ->join('project_tasks', 'project_tasks.task_id', '=', 'project_task_approval.task_id')
        //                         ->orderBy('approval_id', 'desc')
        //                         ->first();

        //     // if($list[$i] != null){
        //     //     array_push($filteredTask, $list[$i]);
        //     // }
        //     $tasks[$i]->subtasks = $list[$i];

        // }

        return response()->json([
            "total" => count($parent),
            "tasks" => $parent
        ], 200, [], JSON_NUMERIC_CHECK);

        // return new TaskResource($parent);
    }

    public function taskHistory($taskId)
    {
        // $employeId = Employe::employeId();
        $history = TaskApproval::join('employees AS A', 'A.employe_id', '=', 'project_task_approval.employe_id')
                    ->join('employees AS B', 'B.employe_id', '=', 'project_task_approval.status_by')
                    ->join('project_tasks AS C', 'C.task_id', '=', 'project_task_approval.task_id')
                    ->join('employees AS D', 'D.employe_id', '=', 'C.created_by')
                    ->select(
                        'project_task_approval.approval_id', 
                        'project_task_approval.status', 
                        'project_task_approval.start_date', 
                        'project_task_approval.end_date', 
                        'project_task_approval.created_at', 
                        'project_task_approval.notes', 
                        'A.first_name as pic_task',
                        'B.first_name as status_by',
                        'D.first_name as created_by'
                    )
                    ->where(['project_task_approval.task_id' => $taskId])
                    ->get();

        return response()->json([
            "status" => true,
            "data" => $history
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function updateStatus(Request $request, $taskId)
    {
        $where = ['task_id' => $taskId];
        $task = TaskApproval::where($where)
                ->orderBy('approval_id', 'desc')
                ->first();

        if($request->status == 1){
            $start_date = $task->start_date; 
        }elseif($request->status >= 2 ){
            $start_date = $task->start_date;
        }else{
            $start_date = null;
        }

        if($request->status === 3 || $request->status === 4){
            $statusBy  = Employe::employeId();
        }else{
            $statusBy  = $request->employe_id;
        }

        $data = [
            "task_id" => $taskId,
            "employe_id" => $request->employe_id,
            "status" => $request->status,
            "notes" => $request->note ? $request->note : null,
            "status_by" => $statusBy,
            'start_date' => $start_date,
            "end_date" => $task->end_date
        ];
    
        $newStatus = new TaskApproval($data);
        $newStatus->save();

        // jika status selain inprogress
        if($request->status !== 0){

            if($request->status !== 1){
                 // get ProjectID
                    $project = Task::select('project_id')
                                ->where('task_id', $taskId)
                                ->first();

                // jika status review
                if($request->status === 2)
                {
                    // get manager
                    $structure = Structure::select('direct_atasan')
                                ->where('employe_id', $request->employe_id)
                                ->first();

                    $manager = $structure->direct_atasan;

                    // data notif
                    $NotifData = [
                        'from_employe' => $request->employe_id,
                        'to_employe' => $manager,
                        'project_id' => $project->project_id,
                        'task_id' => $taskId,
                        'title' => 'Project Task',
                        'desc' => 'Meminta persetujuan Anda',
                        'category' => 'task',
                    ];

                // jika status done
                }elseif($request->status === 3){

                    // data notif
                    $NotifData = [
                        'from_employe' => $statusBy,
                        'to_employe' => $request->employe_id,
                        'project_id' => $project->project_id,
                        'task_id' => $taskId,
                        'title' => 'Project Task',
                        'desc' => 'Menyetujui task Anda',
                        'category' => 'task',
                    ];
                }elseif($request->status === 4){

                    // data notif
                    $NotifData = [
                        'from_employe' => $statusBy,
                        'to_employe' => $request->employe_id,
                        'project_id' => $project->project_id,
                        'task_id' => $taskId,
                        'title' => 'Project Task',
                        'desc' => 'Merevisi task Anda',
                        'category' => 'task',
                    ];
                }

                $newNotification = new Notification($NotifData);
                $newNotification->save();
            }else{
                // jika task pertama inprogress
                // cek status project
                $projectByTask = Task::select('project_tasks.project_id', 'projects.status')
                                ->where('task_id', $task->task_id)
                                ->join('projects', 'projects.project_id', '=', 'project_tasks.project_id')
                                ->first();

                // jika status new
                if($projectByTask->status === 'new'){
                    // update menjadi ongoing
                    $statusUpdated = Project::where('project_id', $projectByTask->project_id)
                            ->update(['status' => 'ongoing']);
                }
            }
           
        }

        return response()->json([
            "status" => true,
            "message" => "Status has been updated.",
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function upload(Request $request, $taskId)
    {
        if($request->hasFile('files')){
            $files = $request->file('files');
        
            $thefile = $files[0]->getClientOriginalName();
            $savedFile  = Storage::disk("public_taskfiles")->put($thefile, file_get_contents($files[0]));

            // $file = $request->file('files');
            // $fileName = $file[0]->getClientOriginalName();
            // $savedFile = $file[0]->storeAs('task', $fileName);
            //If you want to specify the disk, you can pass that as the third parameter.
            // $file->storeAs('task', $fileName, 'task');
            
        }

        if(isset($savedFile)){
            $fileData = [
                'task_id' => $taskId,
                'file_name' => $thefile
            ];
            $newFile = new TaskFile($fileData);
            $newFile->save();

            return response()->json([
                "status" => true,
                "file" => [
                    "file_id" => $newFile->id,
                    "file_name" => $thefile,
                ]
            ], 200, [], JSON_NUMERIC_CHECK);
        } else {
            throw new HttpResponseException(response([
                "error" => "Upload file failed."
            ], 400));
        }
    }

    public function review($projectId)
    {
        $employeId = Employe::employeId();
        
        $where =[
            'project_id' => $projectId,
            'direct_atasan' => $employeId,
            'status' => 2
        ];
        
        $tasks = TaskStatus::where($where)
                ->get();
        
        $taskIds = [];
        if(count($tasks) > 0) {
            for ($t=0; $t < count($tasks); $t++) { 
                $taskIds[] = $tasks[$t]->task_id;

                $tasks[$t]['files'] = TaskFile::select('file_name')
                                        ->where('task_id', $taskIds[$t])
                                        ->get();
                
            }
        }


        return response()->json([
            "status" => true,
            "data" => $tasks,
         ], 200, [], JSON_NUMERIC_CHECK);
        

        // $where = ['projects.project_id' => $projectId, 'projects.division' => $organization->organization_id, 'project_task_approval.status' => 2];
        // $data = TaskApproval::select('project_task_approval.task_id')
        //         ->where($where)
        //         ->join('project_tasks', 'project_tasks.task_id', '=', 'project_task_approval.task_id')
        //         ->join('projects', 'projects.project_id', '=', 'project_tasks.project_id')
        //         ->get();
                
        // $tasks = [];
        // $taskIds = [];
        // for ($i=0; $i < count($data); $i++) { 
        //     array_push($tasks, $data[$i]->task_id);
        // }   

        // $taskIdsWithIndex = array_unique($tasks);

        // // hilangin index tak berurut
        // foreach ($taskIdsWithIndex as $tiwi) {
        //     $taskIds[] = $tiwi;
        // }

        // $reviewTasks = [];
        // for ($rt=0; $rt < count($taskIds); $rt++) { 
        //     $list[$rt] = TaskApproval::select(
        //                 'approval_id',
        //                 'project_task_approval.task_id',
        //                 'project_task_approval.employe_id',
        //                 'employees.first_name',
        //                 'project_tasks.task_title',
        //                 'project_tasks.task_desc',
        //                 'status',
        //                 'start_date',
        //                 'end_date',
        //                 'progress',
        //                 'file',
        //                 'project_task_approval.created_at'
        //             )
        //             ->join('employees', 'employees.employe_id', '=', 'project_task_approval.employe_id')
        //             ->join('project_tasks', 'project_tasks.task_id', '=', 'project_task_approval.task_id')
        //             ->where('project_task_approval.task_id', $taskIds[$rt])
        //             ->orderBy('approval_id', 'desc')
        //             ->first();

        //     $reviewTasks[$rt] = $list[$rt];
        //     $reviewTasks[$rt]->files = TaskFile::select('file_id', 'file_name')
        //                                 ->where('task_id', $taskIds[$rt])
        //                                 ->get();
            
        // }

        return response()->json([
            "total" => count($reviewTasks),
            "data" => $reviewTasks
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function taskByProject(Request $request, $projectId)
    {  

        $employeId = Employe::employeId();
        $query = $request->query('div');

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

        // cari divisi active
        $picActive = ProjectHistory::where(['project_id' => $projectId, 'active' => 1])
                        ->first();

        $divisionActive = Employe::getEmployeDivision($picActive->employe_id);

        $whereParent = ['project_id' => $projectId, 'task_parent' => null, 'division' => $query ? +$query : $divisionActive->organization_id];
        $parent = Task::select(
                    'task_id',
                    'task_parent',
                    'task_title',
                    'task_desc',
                    'task_progress',
                    'project_tasks.created_at',
                    'employees.first_name as created_by'
                )
                ->where($whereParent)->orderBy('project_tasks.task_id', 'desc')
                ->join('employees', 'employees.employe_id', '=', 'project_tasks.created_by')
                ->get();
        $count = count($parent);

        // parent with status || get by employee
        for ($p=0; $p < $count; $p++) { 

            $whereHistory = ['task_id' => $parent[$p]->task_id];
            $lastHistory[$p] = TaskApproval::select('employe_id','status', 'start_date', 'end_date')
                        ->orderBy('approval_id', 'desc')
                        ->where($whereHistory)
                        ->first();


            if($lastHistory[$p] !== null){
                $parent[$p]->employe_id = $lastHistory[$p]->employe_id;
                $parent[$p]->status = $lastHistory[$p]->status;
                $parent[$p]->start_date = (!empty($lastHistory[$p]->start_date)) ? $lastHistory[$p]->start_date : null;
                $parent[$p]->end_date =  $lastHistory[$p]->end_date;

                $whereSubtask = ['project_id' => $projectId, 'task_parent' => $parent[$p]->task_id];
                $subtask[$p] = Task::select(
                                'project_tasks.task_id',
                                'task_parent',
                                'task_title',
                                'task_desc',
                                'task_progress',
                                'project_tasks.created_at',
                                'employees.first_name as created_by'
                            )
                            ->where($whereSubtask)
                            ->join('project_task_approval', 'project_task_approval.task_id', '=', 'project_tasks.task_id')
                            ->join('employees', 'employees.employe_id', '=', 'project_tasks.created_by')
                            ->get();

                $parent[$p]->pics = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                        ->where('task_id', $parent[$p]->task_id)
                        ->join('employees', 'employees.employe_id', '=', 'project_task_pics.employe_id')
                        ->get();

                        
                // comments
                $parent[$p]->comments = Comment::where('task_id', $parent[$p]->task_id)->count();
                        
                // file task
                $parent[$p]->files = TaskFile::select('file_id', 'file_name')
                        ->where('task_id', $parent[$p]->task_id)
                        ->get();  

                // jika ada di list favorite untuk direksi
                $isFavorite[$p] = TaskFavorite::where(['employe_id' => $employeId, 'task_id' => $parent[$p]->task_id])
                                ->first();

                $parent[$p]->isFavorite = $isFavorite[$p] ? true : false;

                // looping subtask
                for ($st=0; $st < count($subtask[$p]); $st++) { 
                    // subtask comments
                    $subtask[$p][$st]->comments = Comment::where('task_id', $subtask[$p][$st]->task_id)->count();

                    // subtask pic
                    $subtask[$p][$st]->pics = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                            ->where('task_id', $subtask[$p][$st]->task_id)
                                            ->join('employees', 'employees.employe_id', '=', 'project_task_pics.employe_id')
                                            ->get();

                    $subtask[$p][$st]->files = TaskFile::select('file_id', 'file_name')
                                            ->where('task_id', $subtask[$p][$st]->task_id)
                                            ->get(); 

                    $isFavoriteSubtask[$st] = TaskFavorite::where(['employe_id' => $employeId, 'task_id' => $subtask[$p][$st]->task_id])
                                            ->first();
            
                    $subtask[$p][$st]->isFavorite = $isFavoriteSubtask[$st] ? true : false;
                }

                $parent[$p]->subtasks = $subtask[$p];  

            }
        };

        return response()->json([
            "total" => count($parent),
            "project" => $fase,
            "data" => $parent,
        ], 200, [], JSON_NUMERIC_CHECK);
    }
    
    public function recentTaskByEmploye($employeId) {

        // cari task inprogres / subtask
        // $status = [0,1];
        // $tasks = TaskApproval::whereIn('status', $status)
        //         ->where('employe_id', $employeId)
        //         ->get();


        // $tasks = TaskApproval::where(['project_task_approval.employe_id' => $employeId, 'project_task_approval.status' => 1, 'project_tasks.task_parent' => null])
        //         ->join('project_tasks', 'project_tasks.task_id','=', 'project_task_approval.task_id')
        //         ->select(
        //             'project_tasks.task_id', 
        //             'project_tasks.project_id', 
        //             'project_tasks.task_title', 
        //             'project_tasks.task_progress',
        //             'project_task_approval.status', 
        //             'project_task_approval.end_date'
        //         )
        //         ->latest('project_task_approval.updated_at')
        //         ->limit(5)
        //         ->get();

        $tasks = TaskStatus::where(['task_latest_status.employe_id' => $employeId, 'task_latest_status.status' => 1, 'project_tasks.task_parent' => null])
                ->join('project_tasks', 'project_tasks.task_id','=', 'task_latest_status.task_id')
                ->select(
                    'project_tasks.task_id', 
                    'project_tasks.project_id', 
                    'project_tasks.task_title', 
                    'project_tasks.task_progress',
                    'task_latest_status.status', 
                    'task_latest_status.end_date'
                )
                ->limit(5)
                ->get();

        return response()->json([
           "status" => true,
           "data" => $tasks
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function addFavoriteTask($employeId, $taskId)
    {
        
        $employeIdActive = Employe::employeId();
        
        if($employeIdActive !== $employeId){
            throw new HttpResponseException(response([
                "error" => "Bad request." 
            ], 400));
        }
        
        $data = ['employe_id' => $employeId, 'task_id' => $taskId];
        $favoriteIsExist = TaskFavorite::where($data)
                        ->first();
        
        if($favoriteIsExist){
            $deleteFavorite = TaskFavorite::where($data)
                            ->delete();

            if($deleteFavorite) {
                return response()->json([
                    "status" => true,
                    "message" => "The task has been removed from the favorites list.",
                ], 200);
            }else{
                throw new HttpResponseException(response([
                    "error" => "Something went wrong." 
                ], 500));
            }
        }

        $newFavoriteTask = new TaskFavorite($data);
        $savedFavorite = $newFavoriteTask->save();

        if($savedFavorite) {
            return response()->json([
                "status" => true,
                "message" => "successfully added the task as a favorite",
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "error" => "Something went wrong." 
            ], 500));
        }
    }

    public function dashboardList(Request $request)
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

        $query = $request->query('type');

        if($query === 'marked'){
            $listTask = TaskFavorite::where('project_task_favorite.employe_id', $employeId)
                    ->join('task_latest_status', 'task_latest_status.task_id', '=', 'project_task_favorite.task_id')
                    ->limit(5)
                    ->get();

        }else if($query === 'done'){
            $listTask = TaskStatus::where('status', 2)
                        ->whereIn('division', $divisionIds)
                        ->limit(5)
                        ->get();
        }else{
            $listTask = TaskStatus::where('status', 1)
                        ->where('task_progress', '>=', 50)
                        ->whereIn('division', $divisionIds)
                        ->limit(5)
                        ->get();
        }

        if(count($listTask) > 0){
            for ($lt=0; $lt < count($listTask); $lt++) { 
                $listTask[$lt]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                            ->where('task_id', $listTask[$lt]->task_id)
                            ->join('employees', 'employees.employe_id', '=', 'project_task_pics.employe_id')
                            ->get();
            }
        }

        return response()->json([
            "status" => true,
            "data" => $listTask,
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function additionalList()
    {

        $employeId = Employe::employeId();
        $employeDivision = Employe::getEmployeDivision($employeId);

        $tasks = TaskPic::join('task_latest_status', 'task_latest_status.task_id', '=', 'project_task_pics.task_id')
                    ->where('project_task_pics.employe_id', $employeId)
                    ->where('task_latest_status.division', '!=', $employeDivision->organization_id)
                    ->whereIn('task_latest_status.status', [0,1,2])
                    ->limit(10)
                    ->get();
                    

        return response()->json([
            "status" => true,
            "total" => count($tasks),
            "data" => $tasks
        ], 200);
    }

    public function inProgressList()
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

        $listTask = TaskStatus::where('status', 1)
                    ->whereIn('division', $divisionIds)
                    ->get();

        if(count($listTask) > 0){
            for ($lt=0; $lt < count($listTask); $lt++) { 
                $listTask[$lt]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                            ->where('task_id', $listTask[$lt]->task_id)
                            ->join('employees', 'employees.employe_id', '=', 'project_task_pics.employe_id')
                            ->get();

                $isFavorite[$lt] = TaskFavorite::where(['employe_id' => $employeId, 'task_id' => $listTask[$lt]->task_id])
                            ->first();

                $listTask[$lt]['isFavorite'] = $isFavorite[$lt] ? true : false;
            }
        }

        return response()->json([
            "status" => true,
            "total" => count($listTask),
            "data" => $listTask
        ], 200);
    }

    // UPDATE AFTER LAUNCHING

    // 1. GET TASK WITH 3 LEVEL
    public function projectTaskByEmploye($projectId)
    {

        $employeId = Employe::employeId();

        // CHECK USER ADALAH DIVISI AKTIF
        $employeDivision = ProjectHistory::select('employe_id')
                        ->where(['project_id' => $projectId, 'active' => 1])
                        ->first();

        if($employeId !== $employeDivision->employe_id){
            $employeCompare = Structure::select('organization_id')
                            ->whereIn('employe_id', [$employeDivision->employe_id, $employeId])
                            ->get();

            $isMemberActive = $employeCompare[0]->organization_id === $employeCompare[1]->organization_id;
        } else {
            // jika user active adalah manager
            $isMemberActive = true;
        }
        // CHECK USER ADALAH DIVISI AKTIF

        // CHECK EMPLOYEE SEBAGAI PIC
        $taskIds = [];
        $taskByPic = TaskPic::where(['project_id' => $projectId, 'employe_id' => $employeId])
                    ->get();

        for ($ti=0; $ti < count($taskByPic); $ti++) { 
            $taskIds[] = $taskByPic[$ti]->task_id;
        };
        // CHECK EMPLOYEE SEBAGAI PIC

        if(count($taskIds) > 0){
            // SEMUA TASK (L1, L2, L3)
            $tasks = TaskStatus::whereIn('task_id', $taskIds)
                    ->get();

            // GET ADDITIONAL DATA TASK
            for ($ts=0; $ts < count($tasks); $ts++) { 
                $tasks[$ts]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                    ->where('task_id', $tasks[$ts]->task_id)
                                    ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                    ->get();

                $tasks[$ts]['comments'] = Comment::where('task_id', $tasks[$ts]->task_id)->count();
                
                $tasks[$ts]['files'] = TaskFile::select('file_id', 'file_name')
                                            ->where('task_id', $tasks[$ts]->task_id)
                                            ->get(); 
            }
            // GET ADDITIONAL DATA TASK

            // DISTRIBUTE TASK
            $level1Ids = [];
            $level2Ids = [];
            $level3Ids = [];

            $level1 = [];
            $level2 = [];
            $level3 = [];

            for ($tk=0; $tk < count($tasks); $tk++) { 
                if($tasks[$tk]->task_parent === null){
                    $level1Ids[] = $tasks[$tk]->task_id;
                    array_push($level1, $tasks[$tk]);
                }elseif(in_array($tasks[$tk]->task_parent, $level1Ids)){
                    $level2Ids[] = $tasks[$tk]->task_id;
                    array_push($level2, $tasks[$tk]);
                }else{
                    $level3Ids[] = $tasks[$tk]->task_id;
                    array_push($level3, $tasks[$tk]);
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
        }

        return response()->json([
            "status" => true,
            "total" => count($level1),
            "is_member_active" => $isMemberActive,
            "data" => $level1,
            "ids" => $taskIds,
            "l1" => $level1Ids,
            "l2" => $level2Ids,
            "l3" => $level3Ids,
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
