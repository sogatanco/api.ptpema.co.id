<?php
public function jaja()
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

?>