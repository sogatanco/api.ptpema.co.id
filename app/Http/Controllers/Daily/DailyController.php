<?php

namespace App\Http\Controllers\Daily;

use App\Http\Controllers\Controller;
use App\Models\Daily\Daily;
use App\Models\Employe;
use App\Models\Projects\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\FormatTanggalRangeIndo;


class DailyController extends Controller
{
    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'task_id' => ['required'],
            'activity_name' => ['required'],
            'start_date' => ['required'],
            'end_date' => ['required'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => $validator->errors()
            ], 500));
        }

        $employeId = Employe::employeId();

        $newDaily = new Daily();
        $newDaily->task_id = $request->task_id;
        $newDaily->employe_id = $employeId;
        $newDaily->activity_name = $request->activity_name;
        $newDaily->progress = 0;
        $newDaily->start_date = $request->start_date;
        $newDaily->end_date = $request->end_date;

        if(!$newDaily->save()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => "Failed to create daily."
            ], 500));
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully created daily."
        ], 200);

    }

    public function update(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => ['required'],
            'activity_name' => ['required'],
            'start_date' => ['required'],
            'end_date' => ['required'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => $validator->errors()
            ], 500));
        }

        $updated = Daily::where('id', $request->id)->update([
            'activity_name' => $request->activity_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        if($updated){
            return response()->json([
                "status" => true,
                "message" => "Successfully updated daily."
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Failed to update daily."
            ], 500);
        }
    }

    public function changeProgress(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => ['required'],
            'progress' => ['required'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => $validator->errors()
            ], 500));
        }

        $updated = Daily::where('id', $request->id)->update([
            'progress' => $request->progress,
        ]);

        if($updated){
            return response()->json([
                "status" => true,
                "message" => "Successfully updated daily."
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Failed to update daily."
            ], 500);
        }
    }

    public function changeStatus(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => ['required'],
            'status' => ['required'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => $validator->errors()
            ], 500));
        }

        if($request->status == 'revised'){

            $data = [
                'progress' => 0,
                'status' => 'revised',
                'notes' => $request->notes
            ];

        }else{

              $data = [
                'status' => $request->status,
              ];

        }

        $updated = Daily::where('id', $request->id)->update($data);

        if($updated){
            return response()->json([
                "status" => true,
                "message" => "Successfully updated daily."
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Failed to update daily."
            ], 500);
        }
    }

    public function delete($id){
        if(Daily::find($id)->delete()){
            return response()->json([
                "status" => true,
                "message" => "Successfully deleted daily."
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Failed to delete daily."
            ], 500);
        }
    }

    public function listByEmployee(Request $request){

        $year = $request->query('year');
        $search = $request->query('search');
        $employeId = Employe::employeId();

        $projects = Project::whereHas('activeStage', function ($query) use ($year) {
                if ($year) {
                    $query->whereYear('start_date', '<=', $year)
                        ->whereYear('end_date', '>=', $year);
                }
            })
            ->when($search, function ($query, $search) {
                $query->where('project_name', 'like', '%' . $search . '%');
            })
            ->whereHas('project_task.pics', function ($query) use ($employeId) {
                $query->where('employe_id', $employeId);
            })
            ->with([
                'activeStage:project_id,start_date,end_date',
                'project_task.pics' => function ($query) use ($employeId) {
                    $query->where('employe_id', $employeId);
                }
            ])
            ->with(['project_task' => function ($query) use ($employeId) {
                $query->whereHas('pics', function ($q) use ($employeId) {
                    $q->where('employe_id', $employeId);
                })
                // Ambil hanya task level 3 (memiliki parent)
                ->whereHas('parent', function ($q) {
                    $q->whereNotNull('task_parent');
                })
                ->with([
                    'daily' => function ($q) use ($employeId) {
                        $q->where('employe_id', $employeId)->orderBy('activity_name');
                    },
                    'approval' => function ($q) {
                        $q->where('status', 0);
                    },
                    'pics.employee' => function ($q) {
                        $q->select('employe_id', 'first_name', 'last_name', 'position_id')
                        ->with('position:position_id,position_name');
                    }
                ])
                ->orderBy('task_title');
            }])
            ->orderBy('project_name')
            ->get();

        $formatted = $projects->map(function ($project) use ($employeId) {

                return [
                    'project_name' => $project->project_name,
                    'start_date' => optional($project->activeStage)->start_date,
                    'end_date' => optional($project->activeStage)->end_date,
                    'date_range' => FormatTanggalRangeIndo( optional($project->activeStage)->start_date, optional($project->activeStage)->end_date),
                    'total_task' => $project->project_task->count(),
                    'project_task' => $project->project_task->map(function ($task) use ($project) {
                        return [
                            'task_id' => $task->task_id,
                            'task_title' => $task->task_title,
                            'start_date' => optional($task->approval)->start_date,
                            'end_date' => optional($task->approval)->end_date,
                            'date_range' => FormatTanggalRangeIndo( optional($task->approval)->start_date, optional($task->approval)->end_date),
                            'task_progress' => $task->task_progress,
                            'members' => $task->pics->map(function ($pic) use ($task) {
                                $employee = $pic->employee;

                                // Ambil semua daily untuk member ini dari task ini
                                $dailies = $task->daily->where('employe_id', $employee->employe_id);
                                $totalDaily = $dailies->count();
                                $totalProgress = $totalDaily > 0 ? round($dailies->sum('progress') / $totalDaily) : 0;

                                return [
                                    'employe_id' => $employee->employe_id,
                                    'name' => trim($employee->first_name . ' ' . $employee->last_name),
                                    'position_name' => optional($employee->position)->position_name,
                                    'progress' => $totalProgress,
                                    'total_daily' => $totalDaily
                                ];
                            }),
                            'daily' => $task->daily->map(function ($d) {

                                $start = $d->start_date ? Carbon::parse($d->start_date) : null;
                                $end = $d->end_date ? Carbon::parse($d->end_date) : null;

                                return [
                                    'id' => $d->id,
                                    'activity_name' => $d->activity_name,
                                    'category' => $d->category,
                                    'status' => $d->status,
                                    'start_date' => [
                                        'date' => $start ? Carbon::parse($start)->format('d-m-Y') : null,
                                        'time' => $start ? Carbon::parse($start)->format('H:i') : null,
                                    ],
                                    'end_date' => [
                                        'date' => $end ? Carbon::parse($end)->format('d-m-Y') : null,
                                        'time' => $end ? Carbon::parse($end)->format('H:i') : null,
                                    ],
                                    'date_range' => $start && $end ? FormatTanggalRangeIndo($start, $end) : null,
                                    'progress' => $d->progress,
                                ];
                            }),
                        ];
                    }),
                ];
            });

        return response()->json([
            "status" => true,
            "total" => count($projects),
            "data" => $formatted
        ], 200);
    }
}
