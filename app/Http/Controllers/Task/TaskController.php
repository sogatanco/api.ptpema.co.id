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
use App\Models\Projects\TaskProgress;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TaskResource;
use App\Http\Requests\TaskRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Notification\NotificationController;

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
        // $employeDivision = Employe::getEmployeDivision($employeId);

        // ambil divisi yang punya projek dan status aktif
        $picActive = ProjectHistory::where(['project_id' => $request->project_id, 'active' => 1])
                    ->first();

        $divisionActive = Employe::getEmployeDivision($picActive->employe_id);

        // save new task
        $data['division'] = $divisionActive->organization_id;
        $data['created_by'] = $employeId;
        $newTask = new Task($data);
        // $newTaskSaved = $newTask->save();
        $newTask->save();

        // Kode jika parent simpan progress
        // if($newTaskSaved){

        //     $task = Task::where('task_id', $newTask->task_id)
        //         ->first();

        //     if($task->task_parent !== null){
        //         // siapa parentnya
        //         $parent = $task->task_parent;

        //         // jumlah subtask berapa berdasarkan parent
        //         $subtaskSum = Task::where('task_parent', $parent)
        //                     ->get();

        //         // jumlahin semua progress
        //         $totalProgress = 0;
        //         $totalSubtask = count($subtaskSum);

        //         for ($sum=0; $sum < $totalSubtask; $sum++) {
        //             $totalProgress = $subtaskSum[$sum]->task_progress + $totalProgress;
        //         }

        //         // total progress dibagi jumlah subtask
        //         $totalPercentage = $totalProgress/$totalSubtask;

        //         // update ke parent
        //         $parentData = [
        //             'task_progress' => $totalPercentage,
        //         ];

        //         Task::where('task_id', $parent)->update($parentData);
        //     }
        // }

        // save filename
        if(isset($savedFile)){
            $fileData = [
                'task_id' => $newTask->task_id,
                'file_name' => $savedFile,
                'employe_id' => $employeId
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
                'task_id' => $newTask->task_id
            ];

            $newTaskPic = new TaskPic($dataPics[$i]);
            $newTaskPic->save();

        }

        // notif ke masing2 pic yang ditag
        $recipients = TaskPic::select('employe_id')
                    ->where('task_id', $newTask->task_id)->get();

        NotificationController::new('TAG_TASK', $recipients, $request->project_id."/".$newTask->task_id);

        $dataApproval = [
            'task_id' => $newTask->task_id,
            'employe_id' => $data['task_pic'][0]['value'],
            'status' => 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date']
        ];

        $newTaskApproval = new TaskApproval($dataApproval);
        $newTaskApproval->save();

        $data = Task::taskProject($newTaskApproval->approval_id);

        return new TaskResource($newTask);

    }

    /**
     * Display the specified resource.
     */
    public function show($taskId)
    {
        $task = TaskStatus::where('task_latest_status.task_id', $taskId)
                ->leftJoin('list_task_final as a', 'a.task_id', '=' , 'task_latest_status.task_id')
                ->select('task_latest_status.*', 'a.progress')
                ->first();

        $task['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                    ->where('task_id', $taskId)
                    ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                    ->get();

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

            $requestPicIds = [];
            for ($i=0; $i < count($request->pic); $i++) {
                array_push($requestPicIds, $request->pic[$i]['value']);

                $where = ['employe_id' => $request->pic[$i]['value'], 'task_id' => $taskId];
                $checkPic = TaskPic::where($where)
                            ->first();

                if(!$checkPic){
                    $save[$i] = [
                        'project_id' => $request->project_id,
                        'employe_id' => $request->pic[$i]['value'],
                        'task_id' => $taskId
                    ];

                    $newTaskPic = new TaskPic($save[$i]);
                    $newTaskPic->save();
                }
            }

            $allPic = TaskPic::where('task_id', $taskId)
                    ->get();

            for ($ap=0; $ap < count($allPic); $ap++) {
                if(!in_array($allPic[$ap]->employe_id, $requestPicIds)){
                 // HAPUS PIC
                 TaskPic::where(['employe_id' => $allPic[$ap]->employe_id, 'task_id' => $taskId])->delete();
                }
             }

            TaskApproval::where('approval_id', $request->approval_id)
                        ->update(['start_date' => $request->start_date, 'end_date' => $request->end_date]);

            $task = Task::where('task_id', $taskId)
                    ->first();

            return response()->json([
                "status" => true,
                "data" => $task,
                "allpic" => $allPic,
                "reqIds" => $requestPicIds,
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

    public function deleteFile($fileId)
    {
        $deleted = TaskFile::where('file_id', $fileId)->delete();

        if($deleted){
            return response()->json([
                "status" => true,
                "message" => "File has been deleted."
            ],200, [], JSON_NUMERIC_CHECK);
        }else{
            throw new HttpResponseException(response([
                "error" => "Delete file failed"
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
            $start_date = $task->start_date;
        }

        if($request->status === 3 || $request->status === 4){
            // status done/revisi
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
                    // user yg request
                    $employeDivision = Employe::getEmployeDivision($request->employe_id);

                    // divisi yg punya projek
                    $picActive = ProjectHistory::where(['project_id' => $project->project_id, 'active' => 1])
                                ->first();

                    $divisionActive = Employe::getEmployeDivision($picActive->employe_id);

                    if($employeDivision->organization_id !== $divisionActive->organization_id){
                        // jika user yg request review dari divisi lain

                        // yang review adalah pic projek/puk
                        $reviewer = $picActive->employe_id;
                    }else{
                        // jika user yg request review dari divisi yg punya projek
                        // get manager/atasan
                        $structure = Structure::select('direct_atasan')
                                    ->where('employe_id', $request->employe_id)
                                    ->first();

                        // yang review adalah pic/direksi
                        $reviewer = $structure->direct_atasan;
                    }

                    // data notif
                    $NotifData = [
                        'from_employe' => $request->employe_id,
                        'to_employe' => $reviewer,
                        'project_id' => $project->project_id,
                        'task_id' => $taskId,
                        'title' => 'Project Task',
                        'desc' => 'Meminta persetujuan Anda',
                        'category' => 'task',
                    ];

                    // notif baru
                    NotificationController::new('REVIEW_TASK', $reviewer, $project->project_id."/".$taskId);

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

                    // notif baru
                    NotificationController::new('APPROVED_TASK', $request->employe_id, $project->project_id."/".$taskId);

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

                    // notif baru
                    NotificationController::new('REVISED_TASK', $request->employe_id,  $project->project_id."/".$taskId);
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

        $employeId = Employe::employeId();

        if(isset($savedFile)){
            $fileData = [
                'task_id' => $taskId,
                'file_name' => $thefile,
                'employe_id' => $employeId
            ];
            $newFile = new TaskFile($fileData);
            $newFile->save();

            // create notification to all pic and direct supervisor
            $recipients = TaskPic::select('employe_id')
                        ->where('task_id', $taskId)->get();

            $directSupervisor = Structure::select('direct_atasan AS employe_id')
                            ->where('employe_id', $employeId)
                            ->first();

            $recipients->push($directSupervisor);

            $projectId = Task::select('project_id')
                        ->where('task_id', $taskId)
                        ->first()
                        ->project_id;


            NotificationController::new('UPLOAD_TASK_FILE', $recipients, $projectId."/".$taskId);

            return response()->json([
                "status" => true,
                "file" => [
                    "file_id" => $newFile->id,
                    "file_name" => $thefile,
                    "employe_id" => $newFile->employe_id
                ],
            ], 200, [], JSON_NUMERIC_CHECK);

        } else {
            throw new HttpResponseException(response([
                "error" => "Upload file failed."
            ], 400));
        }
    }

    public function review($projectId)
    {
        $userRequest = Auth::user();

        $employeId = Employe::employeId();

        if(in_array("Manager", $userRequest->roles)){
            // jika manager
            $employeDivision = Employe::getEmployeDivision($employeId);

            // ambil atasan langsung manager
            $manager = Structure::select('direct_atasan')
                        ->where('employe_id', $employeId)
                        ->first();

            $where =[
                'task_latest_status.project_id' => $projectId,
                'division' => $employeDivision->organization_id,
                'status' => 2,
            ];

            $tasks = TaskStatus::where($where)
                    ->where('direct_atasan', '!=', $manager->direct_atasan)
                    ->get();

        }else{
            // jika direksi
            $where =[
                'project_id' => $projectId,
                'direct_atasan' => $employeId,
                'status' => 2
            ];

            $tasks = TaskStatus::where($where)
                    ->get();

        }

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

        // return response()->json([
        //     "total" => count($reviewTasks),
        //     "data" => $reviewTasks
        // ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function taskByProject(Request $request, $projectId)
    {
        $userRequest = Auth::user();
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

                $all[$at]['comments'] = Comment::where('task_id', $all[$at]->task_id)->count();

                $all[$at]['files'] = TaskFile::select('file_id', 'file_name')
                                            ->where('task_id', $all[$at]->task_id)
                                            ->get();

                if(in_array("Director", $userRequest->roles)){

                    // jika ada di list favorite untuk direksi
                    $isFavorite[$p] = TaskFavorite::where(['employe_id' => $employeId, 'task_id' => $all[$at]->task_id])
                                    ->first();

                    $all[$at]['isFavorite'] = $isFavorite[$p] ? true : false;

                }

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
        $userRoles = Auth::user()->roles;
        $employeId = Employe::employeId();
        $employeDivision = Employe::getEmployeDivision($employeId);

        // if(in_array('Staff', $userRoles)){
        //     // JIKA STAFF - CARI ATASAN LANGSUNG SEBAGAI PIC


        // }{
        //     $tasks = TaskPic::join('task_latest_status', 'task_latest_status.task_id', '=', 'project_task_pics.task_id')
        //             ->where('project_task_pics.employe_id', $employeId)
        //             ->where('task_latest_status.division', '!=', $employeDivision->organization_id)
        //             ->whereIn('task_latest_status.status', [0,1,2])
        //             ->limit(10)
        //             ->get();
        // }

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
    public function projectTaskByEmployeOld($projectId)
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
        $taskIdsTemp = [];
        $taskByPic = TaskPic::where(['project_id' => $projectId, 'employe_id' => $employeId])
                    ->get();

        for ($ti=0; $ti < count($taskByPic); $ti++) {
            $taskIdsTemp[] = $taskByPic[$ti]->task_id;
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
                    ->select('task_latest_status.*', TaskStatus::raw('(SELECT COUNT(*) FROM task_latest_status as child WHERE child.task_parent = task_latest_status.task_id) AS child'))
                    ->get();
        }

        if(count($all) > 0) {
            for ($at=0; $at < count($all); $at++) {
                $all[$at]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                    ->where('task_id', $all[$at]->task_id)
                                    ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                    ->get();

                $all[$at]['comments'] = Comment::where('task_id', $all[$at]->task_id)->count();

                $all[$at]['files'] = TaskFile::select('file_id', 'file_name')
                                            ->where('task_id', $all[$at]->task_id)
                                            ->get();

                $taskProgress = TaskProgress::select('progress')
                                ->where('task_id', $all[$at]->task_id)
                                ->first();

                $all[$at]['task_progress'] = $taskProgress->progress;
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
            "status" => true,
            "is_member_active" => $isMemberActive,
            "total" => count($level1),
            "data" => $level1
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // FUNGSI PENGGANTI projectTaskByEmployeOld SETELAH ADA FITUR PRINTILAN
    public function projectTaskByEmploye($projectId)
    {
        $employeId = Employe::employeId();
        $employeDivision = Employe::getEmployeDivision($employeId);

        // User gak jelas
        $userGakJelas = ['202310079K'];

        // ROLES USER
        $userRoles = Auth::user()->roles;

        // CHECK USER ADALAH DIVISI AKTIF
        $divisionActive = ProjectHistory::select('employe_id')
                        ->where(['project_id' => $projectId, 'active' => 1])
                        ->first();

        if($employeId !== $divisionActive->employe_id){
            // Jika user gak jelas
            if(!in_array($employeId, $userGakJelas)){

                $isMemberActive = true;

            }else{

                // Jika user bukan manager
                $employeCompare = Structure::select('organization_id')
                                ->whereIn('employe_id', [$divisionActive->employe_id, $employeId])
                                ->get();

                $isMemberActive = $employeCompare[0]->organization_id === $employeCompare[1]->organization_id;

            }
        } else {
            // jika user active adalah manager
            $isMemberActive = true;
        }
        // CHECK USER ADALAH DIVISI AKTIF

        // CARI ATASAN LANGSUNG
        $directSupervisor = Structure::select('direct_atasan')
                            ->where('employe_id', $employeId)
                            ->first();

        if($isMemberActive){
            // JIKA USER ADALAH MEMBER DIVISI
            // AMBIL SEMUA TASK BY DIVISI AKTIF KECUALI ADDITIONAL TASK MILIK DIVISI LAIN
            $listOfTask = Task::where(['project_id' => $projectId, 'division' => $employeDivision->organization_id])
                            ->get();

        }else{
            // JIKA USER DARI DIVISI LAIN
            if(in_array('Staff', $userRoles)){
                // CARI USER SEBAGAI PIC
                $whereByUser = ['project_id' => $projectId, 'employe_id' => $employeId];

                $result1 = TaskPic::where($whereByUser)
                        ->get();

                // ATAU CARI ATASAN SEBAGAI PIC TASK YG DI ASSIGN OLEH DIVISI AKTIF
                $whereByDirectSupervisor = ['project_id' => $projectId, 'employe_id' => $directSupervisor->direct_atasan];

                $result2 = TaskPic::where($whereByDirectSupervisor)
                        ->get();

                if(count($result1) > 0 && count($result2) > 0){
                    $listOfTask = array_merge($result1->toArray(), $result2->toArray());
                }elseif(count($result1) > 0){
                    $listOfTask = $result1;
                }elseif(count($result2) > 0){
                    $listOfTask = $result2;
                }else{
                    $listOfTask = [];
                }

            }else{
                // JIKA USER ADALAH SUPERVISOR/MANAGER DARI DIVISI LAIN
                $where = ['project_id' => $projectId, 'employe_id' => $employeId];

                $listOfTask = TaskPic::where($where)
                            ->get();
            }
        }

        $taskIdsTemp = [];
        for ($ti=0; $ti < count($listOfTask); $ti++) {
            if(!in_array($listOfTask[$ti]['task_id'], $taskIdsTemp)){
                array_push($taskIdsTemp, $listOfTask[$ti]['task_id']);
            };
        };

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
                    ->select('task_latest_status.*', TaskStatus::raw('(SELECT COUNT(*) FROM task_latest_status as child WHERE child.task_parent = task_latest_status.task_id) AS child'))
                    ->get();
        }

        if(count($all) > 0) {
            for ($at=0; $at < count($all); $at++) {
                $all[$at]['pics'] = TaskPic::select('project_task_pics.id', 'project_task_pics.employe_id', 'employees.first_name')
                                    ->where('task_id', $all[$at]->task_id)
                                    ->join('employees', 'employees.employe_id','=','project_task_pics.employe_id')
                                    ->get();

                $all[$at]['comments'] = Comment::where('task_id', $all[$at]->task_id)->count();

                $all[$at]['files'] = TaskFile::select('file_id', 'file_name', 'employe_id')
                                            ->where('task_id', $all[$at]->task_id)
                                            ->get();

                $taskProgress = TaskProgress::select('progress')
                                ->where('task_id', $all[$at]->task_id)
                                ->first();

                $all[$at]['task_progress'] = $taskProgress->progress;
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
            "status" => true,
            "is_member_active" => $isMemberActive,
            "direct_supervisor" => $directSupervisor->direct_atasan,
            "total" => count($level1),
            "data" => $level1,
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // add sub activity/task level 3
    public function addSub(Request $request, $taskId)
    {
        $current = Task::where('task_id', $taskId)->first();

        $reqSub = $request->sub;

        $subArr = [];
        if($current->sub !== null){
            $subArr = json_decode($current->sub);
            $reqSub[0]['id'] = uniqid();
            array_push($subArr, $reqSub[0]);
        }else{
            $reqSub[0]['id'] = uniqid();
            $subArr = $reqSub;
        }

        $data = json_encode($subArr);

        $updated = Task::where('task_id', $taskId)->update(['sub' => $data]);

        if($updated){
            $task = Task::where('task_id', $taskId)->first();

            $subArr = json_decode($task->sub);
            $created = [];
            $done = [];

            for ($i=0; $i < count($subArr); $i++) {
                if($subArr[$i]->status === 'checked'){
                    array_push($done, $subArr[$i]->id);
                }{
                    array_push($created, $subArr[$i]->id);
                }
            }

            $progress = count($done) * 100 / count($subArr);

            $updated = Task::where('task_id', $taskId)->update(['task_progress' => $progress]);

            // send notification to tagged pic
            $recipient = $reqSub[0]['employe_id'];

            $projectId = Task::select('project_id')
                        ->where('task_id', $taskId)
                        ->first()
                        ->project_id;

            NotificationController::new('TAG_SUB_ACTIVITY', $recipient, $projectId."/".$taskId);

            return response()->json([
                "status" => true,
                "message" => "Sub activity has been created",
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "error" => "Something went wrong"
            ], 500));
        }

    }

    public function updateSub(Request $request, $taskId)
    {

        if(count($request->sub) > 0){
            $data = json_encode($request->sub);
        }else{
            $data = null;
        };

        $updated = Task::where('task_id', $taskId)->update(['sub' => $data]);

        if($updated){

            if($data !== null){
                $current = Task::where('task_id', $taskId)->first();

                $subArr = json_decode($current->sub);
                $created = [];
                $done = [];

                for ($i=0; $i < count($subArr); $i++) {
                    if($subArr[$i]->status === 'checked'){
                        array_push($done, $subArr[$i]->id);
                    }{
                        array_push($created, $subArr[$i]->id);
                    }
                }

                $progress = count($done) * 100 / count($subArr);
           }else{
                // JIKA SEMUA SUB DIHAPUS
                $progress = 0;
           }

            $updated = Task::where('task_id', $taskId)->update(['task_progress' => $progress]);

            return response()->json([
                "status" => true,
                "message" => "Sub activity has been updated",
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "error" => "Something went wrong"
            ], 500));
        }
    }

    public function duplicateTask($taskId)
    {


        return response()->json([
            "message" => "From duplikat task endpoint"
        ], 200);
    }
}
