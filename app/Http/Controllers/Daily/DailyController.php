<?php

namespace App\Http\Controllers\Daily;

use App\Http\Controllers\Controller;
use App\Models\Daily\Daily;
use App\Models\Daily\DailyAttachment;
use App\Models\Employe;
use App\Models\Daily\DailyLog;
use App\Models\Projects\Project;
use App\Models\Tasks\Task;
use App\Models\Structure;
use App\Models\Hr\Profil;
use Carbon\Carbon;
use App\Helpers\FormatTanggalRangeIndo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DailyController extends Controller
{
public function dashboard(Request $request)
{
    $year = $request->query('year');
    $employeId = auth()->user()->employe_id;

    // Ambil bawahan
    $assignmentsResponse = $this->listAssignments();
    $assignments = $assignmentsResponse->getData(true);

    $bawahanList = collect($assignments['list_bawahan'] ?? []);

    // Daily saya sendiri
    $dailySaya = Daily::where('employe_id', $employeId)
        ->whereYear('start_date', $year)
        ->get();

    // Helper perhitungan
    $countData = function ($items) {
        $total = $items->where('status', '!=', 'cancelled')->count();
        $sumProgress = $items->sum('progress');

        return [
            'tambahan'     => $items->where('category', 'tambahan')->count(),
            'non_tambahan' => $items->where('category', '!=', 'tambahan')->count(),
            'review'       => $items->whereIn('status', ['review supervisor', 'review manager'])->count(),
            'inprogress'   => $items->where('status', 'in progress')->count(),
            'revised'      => $items->where('status', 'revised')->count(),
            'cancelled'    => $items->where('status', 'cancelled')->count(),
            'approved'     => $items->where('status', 'approved')->count(),
            'total'        => $total,
            'progress'     => $total > 0 ? round($sumProgress / $total, 2) : 0,
        ];
    };

    // Daily bawahan per orang
    $dailyBawahan = $bawahanList->map(function ($b) use ($year, $countData) {

        $items = Daily::where('employe_id', $b['employe_id'])
            ->whereYear('start_date', $year)
            ->get();

        return array_merge([
            'employe_id' => $b['employe_id'],
            'name'       => $b['first_name'] ?? null,
            'position'   => $b['position_name'] ?? null,
            'image'      => $b['image'] ?? null
        ], $countData($items));
    });

    return response()->json([
        'daily_saya'     => $countData($dailySaya),
        'daily_bawahan'  => $dailyBawahan,
    ]);
}


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

    public function uploadAttachment(Request $request){
        $validated = $request->validate([
            'daily_id' => 'required|integer|exists:daily,id',
            'files.*' => 'required|file|max:5120', // max 5MB per file
        ]);

        $insertData = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('daily-attachments', 'public');

            $insertData[] = [
                'daily_id'      => $request->daily_id,
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        // 🚀 insert sekaligus
        DB::table('daily_attachments')->insert($insertData);

        return response()->json([
            "status" => true,
            "message" => "Successfully uploaded attachment."
        ], 200);

    }

    public function deleteAttachment(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => ['required'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => $validator->errors()
            ], 500));
        }

        $attachment = DailyAttachment::find($request->id);

        if($attachment){
            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();
            return response()->json([
                "status" => true,
                "message" => "Successfully deleted attachment."
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Failed to delete attachment."
            ], 500);
        }
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

        }
        else{

              $data = [
                'status' => $request->status,
              ];

        }

        $updated = Daily::where('id', $request->id)->update($data);

        if($updated){
            DailyLog::create([
                'daily_id'      => $request->id,
                'employe_id'   => Employe::employeId(),
                'activity_name' => 'Changed status to '.$request->status,
                'notes'         => $request->notes,
            ]);

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

    public function changeType(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => ['required'],
            'is_priority' => ['required','boolean'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => $validator->errors()
            ], 500));
        }

        $updated = Daily::where('id', $request->id)->update([
            'is_priority' => $request->is_priority,
        ]);

        if($updated){
            return response()->json([
                "status" => true,
                "message" => "Successfully updated daily type."
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Failed to update daily type."
            ], 500);
        }
    }

    /*
    public function listByEmployee(Request $request){

        $user = auth()->user();
        $roles = $user->roles;
        $employeId = Employe::where('user_id', $user->id)->first()->employe_id;
        $division = Employe::getEmployeDivision($employeId);

        $year = $request->query('year');
        $search = $request->query('search');
        $employeId = Employe::employeId();

        $isManager = in_array('Manager', $roles);
        $isSupervisor = in_array('Supervisor', $roles);

        $projects = Project::whereHas('activeStage', function ($query) use ($year) {
                if ($year) {
                    $query->whereYear('start_date', '<=', $year)
                        ->whereYear('end_date', '>=', $year);
                }
            })
            // ->when($search, function ($query, $search) {
            //     $query->where('project_name', 'like', '%' . $search . '%');
            // })
            ->whereHas('project_task.pics', function ($query) use ($employeId) {
                $query->where('employe_id', $employeId);
            })
            ->with([
                'activeStage:project_id,start_date,end_date',
                'project_task.pics' => function ($query) use ($employeId) {
                    $query->where('employe_id', $employeId);
                }
            ])
            ->with(['project_task' => function ($query) use ($employeId, $search) {
                $query->whereHas('pics', function ($q) use ($employeId) {
                    $q->where('employe_id', $employeId);
                })
                // Ambil hanya task level 3 (memiliki parent)
                ->whereHas('parent', function ($q) {
                    $q->whereNotNull('task_parent');
                })
                ->with([
                    'daily' => function ($q) use ($employeId) {
                        $q->where('employe_id', $employeId)
                        ->orderBy('activity_name')
                        ->with('attachments')
                        ->with('logs', function ($q) {
                            $q->orderBy('created_at', 'desc')
                            ->where('notes', '!=', null);
                        });
                    },
                    'approval' => function ($q) {
                        $q->where('status', 0);
                    },
                    'pics.employee' => function ($q) {
                        $q->select('employe_id', 'first_name', 'last_name', 'position_id')
                        ->with('position:position_id,position_name');
                    }
                ])->when($search, function ($query, $search) {
                    $query->where('task_title', 'like', '%' . $search . '%');
                })
                ->orderBy('task_title');
            }])
            ->orderBy('project_name')
            ->get();
        
        $reviewProjects = collect();
       if ($isManager) {
            $reviewProjects = Project::whereHas('activeStage', function ($query) use ($year) {
                    if ($year) {
                        $query->whereYear('start_date', '<=', $year)
                            ->whereYear('end_date', '>=', $year);
                    }
                })
                ->where('division', $division->organization_id)
                // pastikan minimal ada 1 task yang punya daily review
                ->whereHas('project_task', function ($q) {
                    $q->whereHas('daily', function ($d) {
                        $d->where('status', 'review manager');
                    });
                })
                // load hanya task yang punya daily review
                ->with(['project_task' => function ($q) {
                    $q->whereHas('daily', function ($d) {
                        $d->where('status', 'review manager');
                    })
                    ->with(['daily' => function ($d) {
                        $d->where('status', 'review manager')
                        ->with('attachments')
                        ->with('logs', function ($q) {
                            $q->orderBy('created_at', 'desc')
                            ->where('notes', '!=', null);
                        });
                    }]);
                }])
                ->get();
        }
        if ($isSupervisor) {
            $reviewProjects = Project::whereHas('activeStage', function ($query) use ($year) {
                    if ($year) {
                        $query->whereYear('start_date', '<=', $year)
                            ->whereYear('end_date', '>=', $year);
                    }
                })
                ->where('division', $division->organization_id)
                // pastikan minimal ada 1 task yang punya daily review
                ->whereHas('project_task', function ($q) {
                    $q->whereHas('daily', function ($d) {
                        $d->where('status', 'review supervisor')
                        ->with('attachments')
                        ->with('logs', function ($q) {
                            $q->orderBy('created_at', 'desc')
                            ->where('notes', '!=', null);
                        });
                    });
                })
                // load hanya task yang punya daily review
                ->with(['project_task' => function ($q) {
                    $q->whereHas('daily', function ($d) {
                        $d->where('status', 'review supervisor');
                    })
                    ->with(['daily' => function ($d) {
                        $d->where('status', 'review supervisor');
                    }]);
                }])
                ->get();
        }



            $formatted = $projects->map(function ($project) use ($employeId, $reviewProjects, $isManager, $isSupervisor) {
                return [
                    'project_id' => $project->project_id,
                    'project_name' => $project->project_name,
                    'start_date' => optional($project->activeStage)->start_date,
                    'end_date' => optional($project->activeStage)->end_date,
                    'date_range' => FormatTanggalRangeIndo::format( optional($project->activeStage)->start_date, optional($project->activeStage)->end_date),
                    'total_task' => $project->project_task->count(),
                    'project_task' => $project->project_task->map(function ($task) use ($project) {
                        return [
                            'task_id' => $task->task_id,
                            'task_title' => $task->task_title,
                            'start_date' => optional($task->approval)->start_date,
                            'end_date' => optional($task->approval)->end_date,
                            'date_range' => FormatTanggalRangeIndo::format( optional($task->approval)->start_date, optional($task->approval)->end_date),
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
                                    'notes' => $d->notes,
                                    'start_date' => [
                                        'date' => $start ? Carbon::parse($start)->format('d-m-Y') : null,
                                        'time' => $start ? Carbon::parse($start)->format('H:i') : null,
                                    ],
                                    'end_date' => [
                                        'date' => $end ? Carbon::parse($end)->format('d-m-Y') : null,
                                        'time' => $end ? Carbon::parse($end)->format('H:i') : null,
                                    ],
                                    'date_range' => $start && $end ? FormatTanggalRangeIndo::format($start, $end) : null,
                                    'progress' => $d->progress,

                                    'attachments' => $d->attachments->map(function ($file) {
                                        return [
                                            'id' => $file->id,
                                            'file_path' => asset('storage/' . $file->file_path),
                                            'original_name' => $file->original_name,
                                            'mime_type' => $file->mime_type,
                                            'size' => $file->size,
                                        ];
                                    }),
                                    'logs' => $d->logs->map(function ($log) {
                                        $logDate = $log->created_at ? Carbon::parse($log->created_at) : null;
                                        return [
                                            'id' => $log->id,
                                            'activity_name' => $log->activity_name,
                                            'notes' => $log->notes,
                                            'created_at' => $logDate ? Carbon::parse($logDate)->format('d-m-Y H:i') : null,
                                        ];
                                    }),
                                ];

                            }),
                        ];
                    }),
                    // 'project_review' =>  $reviewProjects->where('project_id', $project->project_id)->first()
                    'project_review' => $reviewProjects
                        ->where('project_id', $project->project_id)
                        ->flatMap(function ($proj) {
                            return $proj->project_task->map(function ($task) use ($proj) {
                                return [
                                    'task_id' => $task->task_id,
                                    'task_title' => $task->task_title,
                                    'start_date' => optional($task->approval)->start_date,
                                    'end_date' => optional($task->approval)->end_date,
                                    'date_range' => FormatTanggalRangeIndo::format(optional($task->approval)->start_date, optional($task->approval)->end_date),
                                    'task_progress' => $task->task_progress,
                                    'members' => $task->pics->map(function ($pic) {
                                        $employee = $pic->employee;
                                        return [
                                            'employe_id' => $employee->employe_id,
                                            'name' => trim($employee->first_name . ' ' . $employee->last_name),
                                            'position_name' => optional($employee->position)->position_name,
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
                                            'notes' => $d->notes,
                                            'start_date' => [
                                                'date' => $start ? $start->format('d-m-Y') : null,
                                                'time' => $start ? $start->format('H:i') : null,
                                            ],
                                            'end_date' => [
                                                'date' => $end ? $end->format('d-m-Y') : null,
                                                'time' => $end ? $end->format('H:i') : null,
                                            ],
                                            'date_range' => $start && $end ? FormatTanggalRangeIndo::format($start, $end) : null,
                                            'progress' => $d->progress,
                                            'attachments' => $d->attachments->map(function ($file) {
                                                return [
                                                    'id' => $file->id,
                                                    'file_path' => asset('storage/' . $file->file_path),
                                                    'original_name' => $file->original_name,
                                                    'mime_type' => $file->mime_type,
                                                    'size' => $file->size,
                                                ];
                                            }),
                                            'logs' => $d->logs->map(function ($log) {
                                                $logDate = $log->created_at ? Carbon::parse($log->created_at) : null;
                                                return [
                                                    'id' => $log->id,
                                                    'activity_name' => $log->activity_name,
                                                    'notes' => $log->notes,
                                                    'created_at' => $logDate ? $logDate->format('d-m-Y H:i') : null,
                                                ];
                                            }),
                                        ];
                                    }),
                                ];
                            });
                        })
                        ->values(), // ubah ke array index numerik

                ];
            });

        return response()->json([
            "status" => true,
            "total" => count($projects),
            "data" => $formatted
        ], 200);
    }
    */

    public function listByEmployee(Request $request)
    {
        $user = auth()->user();
        $roles = $user->roles;
        $employeId = Employe::where('user_id', $user->id)->first()->employe_id;
        $division = Employe::getEmployeDivision($employeId);

        $year = $request->query('year');
        $month = $request->query('month');
        $search = $request->query('search');
        $category = $request->query('category');
        $is_priority = $request->query('is_priority');
        $type = $request->query('type');
        $status = $request->query('sort');
        $projectId = $request->query('project_id');
        $employeId = $request->query('employe_id', Employe::employeId());

        $isManager = in_array('Manager', $roles);
        $isSupervisor = in_array('Supervisor', $roles);

        if($category === 'tambahan')
        {
            return response()->json([
                'status' => true,
                'total' => 0,
                'data' => []
            ], 200);
        } else {

            // Bagian Projects Utama      
            $projects = Project::whereHas('activeStage', function ($query) use ($year) {
                        // $query->where('status', 1);
                        if ($year) {
                            $query->whereYear('start_date', '<=', $year)
                                ->whereYear('end_date', '>=', $year);
                        }
                    })
                    ->when($projectId, function ($q) use ($projectId) {
                        $q->where('project_id', $projectId);
                    })
                    ->whereHas('project_task.pics', function ($query) use ($employeId) {
                        $query->where('employe_id', $employeId);
                    })
                    ->when($search, function ($query) use ($search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('project_name', 'like', "%{$search}%")
                            ->orWhereHas('project_task', function ($task) use ($search) {
                                $task->where('task_title', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->with([
                        'activeStage:project_id,start_date,end_date',
                        'project_task.pics' => function ($query) use ($employeId) {
                            $query->where('employe_id', $employeId);
                        },
                        'project_task.approval',
                        'project_task.daily.employee', 
                        'project_task.daily.attachments',
                        'project_task.daily.logs',
                        'project_task' => function ($query) use ($month, $employeId, $search, $type, $status, $is_priority) {
                            $query->whereHas('pics', function ($q) use ($employeId) {
                                $q->where('employe_id', $employeId);
                            })
                            ->LevelTwo()
                            ->whereHas('parent.approval', function ($q) {
                                $q->where('status', 1);
                            })
                            ->when($search, function ($q) use ($search) {
                                $q->where('task_title', 'like', "%{$search}%");
                            })
                            ->with([ 'daily' => function ($q) use ($month, $employeId, $type, $status, $is_priority) {
                                $q->where('employe_id', $employeId)
                                ->when($type, function ($query, $type) {
                                    $query->where('category', $type);
                                })
                                ->when($is_priority, function ($query) use ($is_priority) {
                                    if($is_priority === 'true') {
                                        $is_priority = 1;
                                    } else {
                                        $is_priority = 0;
                                    }
                                    $query->where('is_priority', $is_priority);
                                })
                                ->when($status, function ($query, $status) {
                                    if($status == 'review') {
                                        $query->whereIn('status', ['review supervisor', 'review manager']);
                                    }else{
                                        $query->where('status', $status);
                                    } 
                                })->when($month, function ($query) use ($month) {
                                        $query->whereMonth('start_date', $month);
                                    })
                                ->orderBy('created_at', 'desc')
                                    ->with('attachments')
                                    ->with(['logs' => function ($q) {
                                        $q->orderBy('created_at', 'desc');
                                    }]);
                            },
                            'approval', 'pics.employee' => function ($q) {
                                $q->select('employe_id', 'first_name', 'last_name', 'position_id')
                                ->with('position:position_id,position_name');
                            }])
                            ->orderBy('task_title');
                        }
                    ])
                    ->orderBy('project_name')
                    ->get();
            
            // Bagian Review Projects (Hanya tampil jika user adalah Manager/Supervisor dan melihat datanya sendiri) ---
            $reviewProjects = collect();
            if ($isManager && ($employeId === Employe::employeId())) {
                $reviewProjects = Project::whereHas('activeStage', function ($query) use ($year) {
                            if ($year) {
                                $query->whereYear('start_date', '<=', $year)
                                    ->whereYear('end_date', '>=', $year);
                            }
                        })
                        ->where('division', $division->organization_id)
                        // pastikan minimal ada 1 task yang punya daily review
                        ->whereHas('project_task', function ($q) {
                            $q->whereHas('daily', function ($d) {
                                $d->where('status', 'review manager');
                            });
                        })
                        // load hanya task yang punya daily review
                        ->with(['project_task' => function ($q) use ($is_priority, $type) {
                            $q->whereHas('daily', function ($d) {
                                $d->where('status', 'review manager');
                            })
                            ->when($is_priority, function ($d) use ($is_priority, $q) {
                                if($is_priority === 'true') {
                                    $is_priority = 1;
                                } else {
                                    $is_priority = 0;
                                }
                                $q->whereHas('daily', function ($daily) use ($is_priority) {
                                    $daily->where('is_priority', $is_priority);
                                });
                            })
                            ->with(['daily' => function ($d) {
                                $d->where('status', 'review manager')
                                ->with('attachments')
                                ->with('logs', function ($q) {
                                    $q->orderBy('created_at', 'desc');
                                });
                            }]);
                        }])
                        ->get();
            }
            if ($isSupervisor && ($employeId === Employe::employeId())) {
                $reviewProjects = Project::whereHas('activeStage', function ($query) use ($year) {
                            if ($year) {
                                $query->whereYear('start_date', '<=', $year)
                                    ->whereYear('end_date', '>=', $year);
                            }
                        })
                        ->where('division', $division->organization_id)
                        // pastikan minimal ada 1 task yang punya daily review
                        ->whereHas('project_task', function ($q) {
                            $q->whereHas('daily', function ($d) {
                                $d->where('status', 'review supervisor')
                                ->with('attachments')
                                ->with('logs', function ($q) {
                                    $q->orderBy('created_at', 'desc');
                                });
                            });
                        })
                        // load hanya task yang punya daily review
                        ->with(['project_task' => function ($q) {
                            $q->whereHas('daily', function ($d) {
                                $d->where('status', 'review supervisor');
                            })
                            ->when($is_priority, function ($d) use ($is_priority, $q) {
                                if($is_priority === 'true') {
                                    $is_priority = 1;
                                } else {
                                    $is_priority = 0;
                                }
                                $q->whereHas('daily', function ($daily) use ($is_priority) {
                                    $daily->where('is_priority', $is_priority);
                                });
                            })
                            ->with(['daily' => function ($d) {
                                $d->where('status', 'review supervisor');
                            }]);
                        }])
                        ->get();
            }
        
            // --- Format Output ---
            $formatted = $projects->map(function ($project) use ($employeId, $reviewProjects) {
                
                // Is Project Late
                // Project dianggap late jika ada SATU Task saja yang End Date-nya sudah lewat
                $isProjectLate = $project->project_task->contains(function ($task) {
                    $taskEndDate = optional($task->approval)->end_date ? Carbon::parse($task->approval->end_date) : null;
                    
                    // MODIFIKASI: Hanya cek apakah tanggal akhir sudah lewat
                    return $taskEndDate && $taskEndDate->endOfDay()->isPast(); 
                });

                
                return [
                    'project_id' => $project->project_id,
                    'is_late' => $isProjectLate, // is_late untuk Project
                    'project_name' => $project->project_name,
                    'start_date' => optional($project->activeStage)->start_date,
                    'end_date' => optional($project->activeStage)->end_date,
                    'date_range' => FormatTanggalRangeIndo::format(
                        optional($project->activeStage)->start_date,
                        optional($project->activeStage)->end_date
                    ),
                    'total_task' => $project->project_task->count(),
                    'project_task' => $project->project_task->map(function ($task) {
                        
                        // Task terlambat (is_late)
                        $isTaskLate = false;
                        $taskEndDate = optional($task->approval)->end_date ? Carbon::parse($task->approval->end_date) : null;
                        
                        // MODIFIKASI: Hanya cek apakah tanggal akhir sudah lewat
                        if ($taskEndDate && $taskEndDate->endOfDay()->isPast()) {
                            $isTaskLate = true;
                        }

                        return [
                            'task_id' => $task->task_id,
                            'is_late' => $isTaskLate, // is_late untuk Task
                            'task_title' => $task->task_title,
                            'start_date' => optional($task->approval)->start_date,
                            'end_date' => optional($task->approval)->end_date,
                            'date_range' => FormatTanggalRangeIndo::format(
                                optional($task->approval)->start_date,
                                optional($task->approval)->end_date
                            ),
                            'task_progress' => $task->task_progress,
                            'pics' => $task->pics->map(function ($pic) use ($task) {
                                $employee = $pic->employee;
                                $dailies = $task->daily->where('employe_id', $employee->employe_id);
                                $totalDaily = $dailies->count();
                                $totalProgress = $totalDaily > 0
                                    ? round($dailies->sum('progress') / $totalDaily)
                                    : 0;
        
                                return [
                                    'employe_id' => $employee->employe_id,
                                    'name' => trim($employee->first_name . ' ' . $employee->last_name),
                                    'position_name' => optional($employee->position)->position_name,
                                    'progress' => $totalProgress,
                                    'total_daily' => $totalDaily
                                ];
                            }),
                            'approval' => $task->approval,
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
                                $pic = $d->employee;
        
                                return [
                                    'id' => $d->id,
                                    'activity_name' => $d->activity_name,
                                    'employe_id' => $pic?->employe_id,
                                    'category' => $d->category,
                                    'is_priority' => $d->is_priority,
                                    'status' => $d->status,
                                    'notes' => $d->notes,
                                    'start_date' => [
                                        'date' => $start ? $start->format('d-m-Y') : null,
                                        'time' => $start ? $start->format('H:i') : null,
                                    ],
                                    'end_date' => [
                                        'date' => $end ? $end->format('d-m-Y') : null,
                                        'time' => $end ? $end->format('H:i') : null,
                                    ],
                                    'date_range' => $start && $end
                                        ? FormatTanggalRangeIndo::format($start, $end)
                                        : null,
                                    'progress' => $d->progress,
                                    'attachments' => $d->attachments->map(function ($file) {
                                        return [
                                            'id' => $file->id,
                                            'file_path' => asset('storage/' . $file->file_path),
                                            'original_name' => $file->original_name,
                                            'mime_type' => $file->mime_type,
                                            'size' => $file->size,
                                        ];
                                    }),
                                    
                                    'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                                    'logs' => $d->logs->map(function ($log) {
                                        return [
                                            'id' => $log->id,
                                            'employe_id' => $log->employe_id,
                                            'activity_name' => trim($log->employee->first_name . ' ' . $log->employee->last_name) . ' - ' . $log->activity_name,
                                            'notes' => $log->notes,
                                            'created_at' => $log->created_at->format('d-m-Y H:i'),
                                        ];
                                    }),
                                ];
                            }),
                            'total_daily_count' => $task->daily->count(),
                        ];
                    }), 
                    'project_review' => $reviewProjects
                        ->where('project_id', $project->project_id)
                        ->flatMap(function ($proj) {
                            return $proj->project_task->map(function ($task) use ($proj) {
                                return [
                                    'task_id' => $task->task_id,
                                    'task_title' => $task->task_title,
                                    'start_date' => optional($task->approval)->start_date,
                                    'end_date' => optional($task->approval)->end_date,
                                    'date_range' => FormatTanggalRangeIndo::format(optional($task->approval)->start_date, optional($task->approval)->end_date),
                                    'task_progress' => $task->task_progress,
                                    'pics' => $task->pics->map(function ($pic) use ($task) {
                                        $employee = $pic->employee;
                                        $dailies = $task->daily->where('employe_id', $employee->employe_id);
                                        $totalDaily = $dailies->count();
                                        $totalProgress = $totalDaily > 0
                                            ? round($dailies->sum('progress') / $totalDaily)
                                            : 0;
        
                                        return [
                                            'employe_id' => $employee->employe_id,
                                            'name' => trim($employee->first_name . ' ' . $employee->last_name),
                                            'position_name' => optional($employee->position)->position_name,
                                            'progress' => $totalProgress,
                                            'total_daily' => $totalDaily
                                        ];
                                    }),
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
                                            'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                                            'category' => $d->category,
                                            'is_priority' => $d->is_priority,
                                            'status' => $d->status,
                                            'notes' => $d->notes,
                                            'start_date' => [
                                                'date' => $start ? $start->format('d-m-Y') : null,
                                                'time' => $start ? $start->format('H:i') : null,
                                            ],
                                            'end_date' => [
                                                'date' => $end ? $end->format('d-m-Y') : null,
                                                'time' => $end ? $end->format('H:i') : null,
                                            ],
                                            'date_range' => $start && $end ? FormatTanggalRangeIndo::format($start, $end) : null,
                                            'progress' => $d->progress,
                                            'attachments' => $d->attachments->map(function ($file) {
                                                return [
                                                    'id' => $file->id,
                                                    'file_path' => asset('storage/' . $file->file_path),
                                                    'original_name' => $file->original_name,
                                                    'mime_type' => $file->mime_type,
                                                    'size' => $file->size,
                                                ];
                                            }),
                                            'logs' => $d->logs->map(function ($log) {
                                                $employee = $log->employee;
                                                $logDate = $log->created_at ? Carbon::parse($log->created_at) : null;
                                                return [
                                                    'id' => $log->id,
                                                    'activity_name' => trim($log->employee->first_name . ' ' . $log->employee->last_name) . ' - ' . $log->activity_name,
                                                    'notes' => $log->notes,
                                                    'created_at' => $logDate ? $logDate->format('d-m-Y H:i') : null,
                                                ];
                                            }),
                                        ];
                                    }),
                                ];
                            });
                        })
                        ->values(), // ubah ke array index numerik
                ];
            });
        
            return response()->json([
                'status' => true,
                'total' => count($projects),
                'data' => $formatted,
            
            ], 200);
        }
        
    }

    public function reviewList(Request $request)
    {
        // 1. Inisialisasi dan Identifikasi Peran
        $user = auth()->user();
        $roles = optional($user)->roles ?? []; // Amankan dari null user
        
        // Ambil ID Karyawan dan Divisi
        $employeeData = Employe::where('user_id', optional($user)->id)->first();
        if (!$employeeData) {
            return response()->json(['status' => false, 'message' => 'Employee data not found for user.'], 404);
        }
        $employeId = $employeeData->employe_id;
        $division = Employe::getEmployeDivision($employeId);
        
        // Cek Peran
        $isManager = in_array('Manager', $roles);
        $isSupervisor = in_array('Supervisor', $roles);
        
        // Tentukan Status Review yang Relevan
        $reviewStatus = null;
        if ($isManager) {
            $reviewStatus = 'review manager';
        } elseif ($isSupervisor) {
            $reviewStatus = 'review supervisor';
        }
        
        // Jika bukan Manager atau Supervisor, kembalikan kosong.
        if (!$reviewStatus) {
            return response()->json(['status' => true, 'total' => 0, 'data' => []], 200);
        }
        
        // Ambil Filter Query
        $year = $request->query('year');
        $search = $request->query('search');
        $is_priority = $request->query('is_priority');
        $type = $request->query('type');

        // 2. Membangun Query Data Review
        $reviewProjects = Project::query()
            ->whereHas('activeStage', function ($query) use ($year) {
                // Filter Proyek berdasarkan tahun aktif
                if ($year) {
                    $query->whereYear('start_date', '<=', $year)
                        ->whereYear('end_date', '>=', $year);
                }
            })
            // Filter berdasarkan Divisi Manager/Supervisor
            ->where('division', optional($division)->organization_id)
            
            // Memastikan Proyek memiliki minimal 1 task yang sedang di-review
            ->whereHas('project_task', function ($q) use ($reviewStatus) {
                $q->whereHas('daily', function ($d) use ($reviewStatus) {
                    $d->where('status', $reviewStatus);
                });
            })
            
            // Memuat Task dan Daily Activity yang memenuhi kriteria review
            ->with([
                'activeStage:project_id,start_date,end_date',
                'project_task' => function ($q) use ($reviewStatus, $is_priority, $type, $search) {
                    $q->whereHas('daily', function ($d) use ($reviewStatus) {
                        $d->where('status', $reviewStatus);
                    })
                    // Filter Task jika ada search
                    ->when($search, function ($q) use ($search) {
                        $q->where('task_title', 'like', "%{$search}%");
                    })
                    // Muat relasi Task lainnya
                    ->with('approval')
                    ->with(['pics.employee' => function ($empQ) {
                        $empQ->select('employe_id', 'first_name', 'last_name', 'position_id')
                            ->with('position:position_id,position_name');
                    }])
                    // Muat Daily Activity yang sedang di-review (dengan filter tambahan)
                    ->with(['daily' => function ($d) use ($reviewStatus, $is_priority, $type) {
                        $d->where('status', $reviewStatus)
                        ->when($type, function ($query, $type) {
                            $query->where('category', $type);
                        })
                        ->when($is_priority, function ($query) use ($is_priority) {
                            $is_priority_value = ($is_priority === 'true') ? 1 : 0;
                            $query->where('is_priority', $is_priority_value);
                        })
                        ->with('attachments')
                        ->with(['logs' => function ($q) {
                            $q->orderBy('created_at', 'desc');
                        }])
                        ->with('employee:employe_id,first_name,last_name'); // Muat pembuat Daily
                    }]);
            }])
            ->orderBy('project_name')
            ->get();

        // 3. Format Output Data
        $formatted = $reviewProjects->map(function ($project) {
            return [
                'project_id' => $project->project_id,
                'project_name' => $project->project_name,
                'start_date' => optional($project->activeStage)->start_date,
                'end_date' => optional($project->activeStage)->end_date,
                'date_range' => class_exists('App\Helpers\FormatTanggalRangeIndo') ? 
                                FormatTanggalRangeIndo::format(optional($project->activeStage)->start_date, optional($project->activeStage)->end_date) : 
                                null,
                'project_review' => $project->project_task->map(function ($task) {
                    // Task Level Formatting
                    return [
                        'task_id' => $task->task_id,
                        'task_title' => $task->task_title,
                        'start_date' => optional($task->approval)->start_date,
                        'end_date' => optional($task->approval)->end_date,
                        // Asumsi FormatTanggalRangeIndo::format tersedia
                        'date_range' => class_exists('App\Helpers\FormatTanggalRangeIndo') ? 
                                        FormatTanggalRangeIndo::format(optional($task->approval)->start_date, optional($task->approval)->end_date) : 
                                        null,
                        'task_progress' => $task->task_progress,
                        'members' => $task->pics->map(function ($pic) use ($task) {
                            $employee = $pic->employee;
                                $dailies = $task->daily->where('employe_id', $employee->employe_id);
                                $totalDaily = $dailies->count();
                                $totalProgress = $totalDaily > 0
                                    ? round($dailies->sum('progress') / $totalDaily)
                                    : 0;
        
                                return [
                                    'employe_id' => $employee->employe_id,
                                    'name' => trim($employee->first_name . ' ' . $employee->last_name),
                                    'position_name' => optional($employee->position)->position_name,
                                    'progress' => $totalProgress,
                                    'total_daily' => $totalDaily
                                ];
                        }),
                        'daily_review' => $task->daily->map(function ($d) {
                            // Daily Activity Level Formatting
                            $start = $d->start_date ? Carbon::parse($d->start_date) : null;
                            $end = $d->end_date ? Carbon::parse($d->end_date) : null;
                            
                            return [
                                'id' => $d->id,
                                'activity_name' => $d->activity_name,
                                'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                                'employe_id' => $d->employe_id,
                                'category' => $d->category,
                                'is_priority' => $d->is_priority,
                                'status' => $d->status,
                                'notes' => $d->notes,
                                'progress' => $d->progress,
                                'start_date' => ['date' => $start?->format('d-m-Y'), 'time' => $start?->format('H:i')],
                                'end_date' => ['date' => $end?->format('d-m-Y'), 'time' => $end?->format('H:i')],
                                'date_range' => class_exists('App\Helpers\FormatTanggalRangeIndo') && $start && $end ? 
                                                FormatTanggalRangeIndo::format($start, $end) : 
                                                null,
                                'attachments' => $d->attachments->map(function ($file) {
                                    return [
                                        'id' => $file->id,
                                        'file_path' => asset('storage/' . $file->file_path),
                                        'original_name' => $file->original_name,
                                        'mime_type' => $file->mime_type,
                                        'size' => $file->size,
                                    ];
                                }),
                                'logs' => $d->logs->map(function ($log) {
                                    return [
                                        'id' => $log->id,
                                        'activity_name' => trim(optional($log->employee)->first_name . ' ' . optional($log->employee)->last_name) . ' - ' . $log->activity_name,
                                        'notes' => $log->notes,
                                        'created_at' => optional($log->created_at)->format('d-m-Y H:i'),
                                    ];
                                }),
                            ];
                        })
                    ];
                })->filter(function ($task) {
                    return $task['daily_review']->isNotEmpty();
                })->values()
            ];
        })->filter(function ($project) {
            return $project['project_review']->isNotEmpty();
        })->values();

        // 4. Respon
        return response()->json([
            'status' => true,
            'total' => $formatted->count(),
            'data' => $formatted,
        ], 200);
    }

    public function approvedListByEmployee(Request $request)
    {
        // 1. Inisialisasi dan Identifikasi Peran
        $user = auth()->user();
        $roles = optional($user)->roles ?? [];
        
        // Ambil ID Karyawan dan Divisi
        $employeeData = Employe::where('user_id', optional($user)->id)->first();
        if (!$employeeData) {
            return response()->json(['status' => false, 'message' => 'Employee data not found for user.'], 404);
        }
        $employeId = $employeeData->employe_id;
        $division = Employe::getEmployeDivision($employeId);
        
        // Cek Roles
        $isManager = in_array('Manager', $roles);
        $isSupervisor = in_array('Supervisor', $roles);
        
        
        // Ambil Filter Query
        $year = $request->query('year');
        $search = $request->query('search');
        $is_priority = $request->query('is_priority');
        $type = $request->query('type');

        // 2. Membangun Query Data Review
        $approvedProjects = Project::query()
            ->whereHas('activeStage', function ($query) use ($year) {
                // Filter Proyek berdasarkan tahun aktif
                if ($year) {
                    $query->whereYear('start_date', '<=', $year)
                        ->whereYear('end_date', '>=', $year);
                }
            })
            // Filter berdasarkan Divisi Manager/Supervisor
            ->where('division', optional($division)->organization_id)
            
            // Memastikan Proyek memiliki minimal 1 task yang sedang di-review
            ->whereHas('project_task', function ($q)  {
                $q->whereHas('daily', function ($d)  {
                    $d->where('status', 'approved');
                });
            })
            
            // Memuat Task dan Daily Activity yang memenuhi kriteria review
            ->with([
                'activeStage:project_id,start_date,end_date',
                'project_task' => function ($q) use ( $is_priority, $type, $search) {
                    $q->whereHas('daily', function ($d)  {
                        $d->where('status', 'approved');
                    })
                    // Filter Task jika ada search
                    ->when($search, function ($q) use ($search) {
                        $q->where('task_title', 'like', "%{$search}%");
                    })
                    // Muat relasi Task lainnya
                    ->with('approval')
                    ->with(['pics.employee' => function ($empQ) {
                        $empQ->select('employe_id', 'first_name', 'last_name', 'position_id')
                            ->with('position:position_id,position_name');
                    }])
                    // Muat Daily Activity yang sedang di-review (dengan filter tambahan)
                    ->with(['daily' => function ($d) use ($is_priority, $type) {
                        $d->where('status', 'approved')
                        ->when($type, function ($query, $type) {
                            $query->where('category', $type);
                        })
                        ->when($is_priority, function ($query) use ($is_priority) {
                            $is_priority_value = ($is_priority === 'true') ? 1 : 0;
                            $query->where('is_priority', $is_priority_value);
                        })
                        ->with('attachments')
                        ->with(['logs' => function ($q) {
                            $q->orderBy('created_at', 'desc');
                        }])
                        ->with('employee:employe_id,first_name,last_name'); // Muat pembuat Daily
                    }]);
            }])
            ->orderBy('project_name')
            ->get();

        // 3. Format Output Data
        $formatted = $approvedProjects->map(function ($project) {
            return [
                'project_id' => $project->project_id,
                'project_name' => $project->project_name,
                'start_date' => optional($project->activeStage)->start_date,
                'end_date' => optional($project->activeStage)->end_date,
                'date_range' => class_exists('App\Helpers\FormatTanggalRangeIndo') ? 
                                FormatTanggalRangeIndo::format(optional($project->activeStage)->start_date, optional($project->activeStage)->end_date) : 
                                null,
                'project_task' => $project->project_task->map(function ($task) {
                    // Task Level Formatting
                    return [
                        'task_id' => $task->task_id,
                        'task_title' => $task->task_title,
                        'start_date' => optional($task->approval)->start_date,
                        'end_date' => optional($task->approval)->end_date,
                        // Asumsi FormatTanggalRangeIndo::format tersedia
                        'date_range' => class_exists('App\Helpers\FormatTanggalRangeIndo') ? 
                                        FormatTanggalRangeIndo::format(optional($task->approval)->start_date, optional($task->approval)->end_date) : 
                                        null,
                        'task_progress' => $task->task_progress,
                        'members' => $task->pics->map(function ($pic) {
                            return [
                                'employe_id' => optional($pic->employee)->employe_id,
                                'name' => trim(optional($pic->employee)->first_name . ' ' . optional($pic->employee)->last_name),
                                'position_name' => optional(optional($pic->employee)->position)->position_name,
                            ];
                        }),
                        'daily' => $task->daily->map(function ($d) {
                            // Daily Activity Level Formatting
                            $start = $d->start_date ? Carbon::parse($d->start_date) : null;
                            $end = $d->end_date ? Carbon::parse($d->end_date) : null;
                            
                            return [
                                'id' => $d->id,
                                'activity_name' => $d->activity_name,
                                'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                                'employe_id' => $d->employe_id,
                                'category' => $d->category,
                                'is_priority' => $d->is_priority,
                                'status' => $d->status,
                                'notes' => $d->notes,
                                'progress' => $d->progress,
                                'start_date' => ['date' => $start?->format('d-m-Y'), 'time' => $start?->format('H:i')],
                                'end_date' => ['date' => $end?->format('d-m-Y'), 'time' => $end?->format('H:i')],
                                'date_range' => class_exists('App\Helpers\FormatTanggalRangeIndo') && $start && $end ? 
                                                FormatTanggalRangeIndo::format($start, $end) : 
                                                null,
                                'attachments' => $d->attachments->map(function ($file) {
                                    return [
                                        'id' => $file->id,
                                        'file_path' => asset('storage/' . $file->file_path),
                                        'original_name' => $file->original_name,
                                        'mime_type' => $file->mime_type,
                                        'size' => $file->size,
                                    ];
                                }),
                                'logs' => $d->logs->map(function ($log) {
                                    return [
                                        'id' => $log->id,
                                        'activity_name' => trim(optional($log->employee)->first_name . ' ' . optional($log->employee)->last_name) . ' - ' . $log->activity_name,
                                        'notes' => $log->notes,
                                        'created_at' => optional($log->created_at)->format('d-m-Y H:i'),
                                    ];
                                }),
                            ];
                        })
                    ];
                })->filter(function ($task) {
                    return $task['daily']->isNotEmpty();
                })->values()
            ];
        })->filter(function ($project) {
            return $project['project_task']->isNotEmpty();
        })->values();

        // 4. Respon
        return response()->json([
            'status' => true,
            'total' => $formatted->count(),
            'data' => $formatted,
        ], 200);
    }
        

        
        // public function listByEmployee(Request $request)
        // {
        //     $year = $request->query('year');
        //     $employeId = $request->query('employe_id', Employe::employeId());

        //     $projects = Project::whereHas('activeStage', function ($query) use ($year) {
        //             if ($year) {
        //                 $query->whereYear('start_date', '<=', $year)
        //                     ->whereYear('end_date', '>=', $year);
        //             }
        //         })
        //         ->whereHas('project_task.pics', function ($query) use ($employeId) {
        //             $query->where('employe_id', $employeId);
        //         })
        //         ->get(); 

        //             return response()->json([
        //                 'status' => true,
        //                 'data' => $projects
        //             ], 200);
        // }

        // public function projectList(Request $request)
        // {
        //     $year = $request->query('year');
        //     $employeId = $request->query('employe_id', Employe::employeId());

        //     $projects = Project::select('project_id', 'project_name', 'category')
        //         ->with([
        //             'activeStage' => function ($query) {
        //                 $query->select(
        //                     'project_id',
        //                     'start_date',
        //                     'end_date',
        //                 );
        //             }
        //         ])
        //         ->whereHas('activeStage', function ($query) use ($year) {
        //             if ($year) {
        //                 $query->whereYear('start_date', '<=', $year)
        //                     ->whereYear('end_date', '>=', $year);
        //             }
        //         })
        //         ->whereHas('project_task.pics', function ($query) use ($employeId) {
        //             $query->where('employe_id', $employeId);
        //         })
        //         ->get();

        //     $formatted = $projects->map(function ($project) {
        //         $stage = $project->activeStage;

        //         return [
        //             'project_id'   => $project->project_id,
        //             'project_name' => $project->project_name,
        //             'category'     => $project->category,
        //             'start_date'   => $stage->start_date ?? null,
        //             'end_date'     => $stage->end_date ?? null,
        //             'date_range'   => $stage ? FormatTanggalRangeIndo::format($stage->start_date, $stage->end_date) : null,
        //             'progress'     => $stage->progress ?? null,
        //             'status'       => $stage->status ?? null,
        //         ];
        //     });

    

        //         return response()->json([
        //             'status' => true,
        //             'data' => $formatted
        //         ], 200);
        // }

        // public function taskList(Request $request)
        // {
        //     $projectId = $request->query('project_id');
        //     $projectTask = Task::where('project_id', $projectId)
        //         ->levelTwo()
        //         ->whereHas('parent.approval', function ($q) {
        //             $q->where('status', 1);   // parent approval status 1
        //         })
        //         ->with([
        //             'approval',
        //             'parent.approval' => function ($q) {
        //                 $q->select(
        //                     'project_task_approval.task_id',
        //                     'project_task_approval.status'
        //                 );
        //             },
        //             'pics',
        //         ])
        //         ->get();

        //         $formatted = $projectTask->map(function ($task) {
        //             return [
        //                 'task_id'      => $task->task_id,
        //                 'project_id'   => $task->project_id,
        //                 'task_title'   => $task->task_title,
        //                 'task_parent'  => $task->task_parent,
        //                 'task_progress'=> $task->task_progress,
        //                 'start_date'   => $task->approval->start_date,
        //                 'end_date'     => $task->approval->end_date,
        //                 'date_range'   => FormatTanggalRangeIndo::format($task->approval->start_date, $task->approval->end_date),
        //                 'members'      => $task->pics->map(function ($pic) use ($task) {
        //                                     $employee = $pic->employee;

        //                                     // Ambil semua daily untuk member ini dari task ini
        //                                     $dailies = $task->daily->where('employe_id', $employee->employe_id);
        //                                     $totalDaily = $dailies->count();
        //                                     $totalProgress = $totalDaily > 0 ? round($dailies->sum('progress') / $totalDaily) : 0;

        //                                     return [
        //                                         'employe_id' => $employee->employe_id,
        //                                         'name' => trim($employee->first_name . ' ' . $employee->last_name),
        //                                         'position_name' => optional($employee->position)->position_name,
        //                                         'progress' => $totalProgress,
        //                                         'total_daily' => $totalDaily
        //                                     ];
        //                                 }),

        //                 // Parent info (jika ada)
        //                 'parent' => [
        //                     'task_id'   => optional($task->parent)->task_id,
        //                     'task_title'=> optional($task->parent)->task_title,

        //                     // Ambil hanya STATUS dari approval parent
        //                     'status'    => optional(optional($task->parent)->approval)->status,
        //                 ],
        //             ];
        //         });





        //     return response()->json([
        //         'status' => true,
        //         'data' => $formatted
        //     ], 200);
        // }

        // public function dailyList(Request $request)
        // {
        //     $parentId = $request->query('parent_id');

        //     $dailies = Daily::where('task_id', $parentId)->with([
        //         'employee'
        //     ])->get();

        //     $formatted = $dailies->map(function ($daily) {
        //         return [
        //             'id' => $daily->id,
        //             'activity_name' => $daily->activity_name,
        //             'employe_id' => $daily->employe_id,
        //             'created_by' => $daily->employee->first_name . ' ' . $daily->employee->last_name,
        //             'progress' => $daily->progress,
        //             'status' => $daily->status,
        //             'notes' => $daily->notes,
        //             'start_date' => $daily->start_date,
        //             'end_date' => $daily->end_date,
        //             'date_range' => FormatTanggalRangeIndo::format($daily->start_date, $daily->end_date),
        //             'attachments' => $daily->attachments,
        //             'logs' => $daily->logs,
        //             'created_at' => $daily->created_at,
        //             'updated_at' => $daily->updated_at
        //         ];
        //     });

        //     return response()->json([
        //         'status' => true,
        //         'data' => $formatted
        //     ]);
        // }

        // public function additional(Request $request)
        // {
        //     $user = auth()->user();
        //     $roles = $user->roles;
        //     $employeId = Employe::where('user_id', $user->id)->first()->employe_id;
        //     $division = Employe::getEmployeDivision($employeId);

        //     $isManager = in_array('Manager', $roles);
        //     $isSupervisor = in_array('Supervisor', $roles);

        //     $dailies = Daily::where('employe_id', $employeId)
        //         ->when($request->category, function ($q) use ($request) {
        //             $q->where('category', $request->category);
        //         })
        //         ->with('task')
        //         ->with('attachments')
        //         ->with(['logs' => function ($q) {
        //             $q->orderBy('created_at', 'desc');
        //         }])
        //         ->orderBy('created_at', 'desc')
        //         ->get();

        //     if($isSupervisor){
        //         // Cari staf di divisi yang sama
        //         $staffIds = Employe::query()
        //                     ->join('positions', 'positions.position_id', '=', 'employees.position_id')
        //                     ->where('positions.organization_id', $division->organization_id)
        //                     ->pluck('employees.employe_id')
        //                     ->toArray();

        //         // Ambil Tugas Tambahan mereka Yang Status Review Supervisor Jika Ada
        //         $reviewProjects = Daily::whereIn('employe_id', $staffIds)
        //             ->where('status', 'review supervisor')
        //             // ->with('task')
        //             ->with('attachments')
        //             ->with(['logs' => function ($q) {
        //                 $q->orderBy('created_at', 'desc');
        //             }])
        //             ->orderBy('created_at', 'desc')
        //             ->get();
        //     }

        //     if($isManager){
        //         // Cari staf di divisi yang sama
        //         $staffIds = Employe::query()
        //                     ->join('positions', 'positions.position_id', '=', 'employees.position_id')
        //                     ->where('positions.organization_id', $division->organization_id)
        //                     ->pluck('employees.employe_id')
        //                     ->toArray();

        //         // Ambil Tugas Tambahan mereka Yang Status Review Manager Jika Ada
        //         $reviewProjects = Daily::whereIn('employe_id', $staffIds)
        //             ->where('status', 'review manager')
        //             // ->with('task')
        //             ->with('attachments')
        //             ->with(['logs' => function ($q) {
        //                 $q->orderBy('created_at', 'desc');
        //             }])
        //             ->orderBy('created_at', 'desc')
        //             ->get();
        //     }


        // }

        public function additional(Request $request)
        {
            $user = auth()->user();
            $roles = $user->roles;
            $year = $request->query('year');
            $month = $request->query('month');
            $employe = Employe::where('user_id', $user->id)->first();
            $employeId = $employe->employe_id;
            $division = Employe::getEmployeDivision($employeId);
            $employeId = $request->query('employe_id', Employe::employeId());

            $isManager = in_array('Manager', $roles);
            $isSupervisor = in_array('Supervisor', $roles);
            $status = $request->query('sort');
            $category = $request->query('category');
            $is_priority = $request->query('is_priority');
            if($is_priority === 'true') {
                $is_priority = true;
            } elseif($is_priority === 'false') {
                $is_priority = false;
            }else{
                $is_priority = null;
            }


            if($category === 'projects') {
                return response()->json([
                    'status' => true,
                    'total' => 0,
                    'data' => []
                ], 200);
            }




            // --- Ambil daily tambahan user sendiri ---
            $dailies = Daily::where('category', 'tambahan')
                ->where('employe_id', $employeId)
                ->when($year, function ($query) use ($year) {
                    $query->whereYear('start_date', $year);
                })
                ->when($month, function ($query) use ($month) {
                    $query->whereMonth('start_date', $month);
                })
                ->when($is_priority !== null, function ($query) use ($is_priority) {
                    $query->where('is_priority', $is_priority);
                })
                ->when($status, function ($query) use ($status) {
                    if($status == 'review') {
                        $query->whereIn('status', ['review supervisor', 'review manager']);
                    }else{
                        $query->where('status', $status);
                    } 
                })
                ->with(['attachments', 'logs' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                }])
                ->orderBy('start_date')
                ->get();

            // --- Ambil daily review manager/supervisor ---

            $reviewDailies = collect();

            if ($isManager && ($employeId === Employe::employeId())) {
                $reviewDailies = Daily::where('category', 'tambahan')
                    ->where('status', 'review manager')
                    ->with(['attachments', 'logs' => function ($q) {
                        $q->orderBy('created_at', 'desc');
                    }])
                    ->orderBy('start_date')
                    ->get();
            }

            if ($isSupervisor && ($employeId === Employe::employeId())) {
                $reviewDailies = Daily::where('category', 'tambahan')
                    ->where('status', 'review supervisor')
                    ->with(['attachments', 'logs' => function ($q) {
                        $q->orderBy('created_at', 'desc');
                    }])
                    ->orderBy('start_date')
                    ->get();
            }

            // --- Format hasil ---
            $formatted = $dailies->map(function ($d) {
                $start = $d->start_date ? Carbon::parse($d->start_date) : null;
                $end = $d->end_date ? Carbon::parse($d->end_date) : null;

                return [
                    'id' => $d->id,
                    'task_id' => $d->task_id,
                    'employe_id' => $d->employe_id,
                    'activity_name' => $d->activity_name,
                    'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                    'category' => $d->category,
                    'is_priority' => $d->is_priority,
                    'status' => $d->status,
                    'notes' => $d->notes,
                    'start_date' => [
                        'date' => $start ? $start->format('d-m-Y') : null,
                        'time' => $start ? $start->format('H:i') : null,
                    ],
                    'end_date' => [
                        'date' => $end ? $end->format('d-m-Y') : null,
                        'time' => $end ? $end->format('H:i') : null,
                    ],
                    'date_range' => $start && $end
                        ? FormatTanggalRangeIndo::format($start, $end)
                        : null,
                    'progress' => $d->progress,
                    'attachments' => $d->attachments->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_path' => asset('storage/' . $file->file_path),
                            'original_name' => $file->original_name,
                            'mime_type' => $file->mime_type,
                            'size' => $file->size,
                        ];
                    }),
                    'logs' => $d->logs->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'employe_id' => $log->employe_id,
                            'activity_name' => trim($log->employee->first_name . ' ' . $log->employee->last_name) . ' - ' . $log->activity_name,
                            'notes' => $log->notes,
                            'created_at' => $log->created_at->format('d-m-Y H:i'),
                        ];
                    }),
                ];
            });

            $formattedReview = $reviewDailies->map(function ($d) {
                $start = $d->start_date ? Carbon::parse($d->start_date) : null;
                $end = $d->end_date ? Carbon::parse($d->end_date) : null;

                return [
                    'id' => $d->id,
                    'activity_name' => $d->activity_name,
                    'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                    'category' => $d->category,
                    'is_priority' => $d->is_priority,
                    'status' => $d->status,
                    'notes' => $d->notes,
                    'start_date' => [
                        'date' => $start ? $start->format('d-m-Y') : null,
                        'time' => $start ? $start->format('H:i') : null,
                    ],
                    'end_date' => [
                        'date' => $end ? $end->format('d-m-Y') : null,
                        'time' => $end ? $end->format('H:i') : null,
                    ],
                    'date_range' => $start && $end
                        ? FormatTanggalRangeIndo::format($start, $end)
                        : null,
                    'progress' => $d->progress,
                    'attachments' => $d->attachments->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_path' => asset('storage/' . $file->file_path),
                            'original_name' => $file->original_name,
                            'mime_type' => $file->mime_type,
                            'size' => $file->size,
                        ];
                    }),
                    'logs' => $d->logs->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'employe_id' => $log->employe_id,
                            'activity_name' => trim($log->employee->first_name . ' ' . $log->employee->last_name) . ' - ' . $log->activity_name,
                            'notes' => $log->notes,
                            'created_at' => $log->created_at->format('d-m-Y H:i'),
                        ];
                    }),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Berhasil mengambil data kategori tambahan',
                'total' => $dailies->count(),
                'data' => [
                    'category' => 'tambahan',
                    'task_progress' => round(
                                            $dailies->where('employe_id', $employeId)
                                                    ->where('category', 'tambahan')
                                                    ->where('status', '!=','cancelled')
                                                    ->avg('progress'), 
                                            2 // Maksimal 2 angka di belakang koma
                                        ) ?? 0,
                    // 'task_progress' => $dailies->where('employe_id', $employeId)->where('category', 'tambahan')->where('status', '!=','cancelled')->avg('progress') ?? 0,
                    'date_range' => now()->format('d F Y'),
                    'daily' => $formatted,
                    'daily_review' => $formattedReview
                ]
            ]);
        }

        // public function additionalReview(Request $request)
        // {
        //     $user = auth()->user();
        //     $roles = $user->roles;
        //     $year = $request->query('year');
        //     $month = $request->query('month');
        //     $employe = Employe::where('user_id', $user->id)->first();
        //     $employeId = $employe->employe_id;
        //     $division = Employe::getEmployeDivision($employeId);
        //     $employeId = $request->query('employe_id', Employe::employeId());

        //     $isManager = in_array('Manager', $roles);
        //     $isSupervisor = in_array('Supervisor', $roles);
        //     $status = $request->query('sort');
        //     $category = $request->query('category');
        //     $is_priority = $request->query('is_priority');
        //     if($is_priority === 'true') {
        //         $is_priority = true;
        //     } elseif($is_priority === 'false') {
        //         $is_priority = false;
        //     }else{
        //         $is_priority = null;
        //     }


        //     if($category === 'projects') {
        //         return response()->json([
        //             'status' => true,
        //             'total' => 0,
        //             'data' => []
        //         ], 200);
        //     }

        //     // --- Ambil daily review manager/supervisor ---
        //     $reviewDailies = collect();

        //     if ($isManager && ($employeId === Employe::employeId())) {
        //         $reviewDailies = Daily::where('category', 'tambahan')
        //             ->where('status', 'review manager')
        //             ->with(['attachments', 'logs' => function ($q) {
        //                 $q->orderBy('created_at', 'desc');
        //             }])
        //             ->orderBy('start_date')
        //             ->get();
        //     }

        //     if ($isSupervisor && ($employeId === Employe::employeId())) {
        //         $reviewDailies = Daily::where('category', 'tambahan')
        //             ->where('status', 'review supervisor')
        //             ->with(['attachments', 'logs' => function ($q) {
        //                 $q->orderBy('created_at', 'desc');
        //             }])
        //             ->orderBy('start_date')
        //             ->get();
        //     }

        //     $formattedReview = $reviewDailies->map(function ($d) {
        //         $start = $d->start_date ? Carbon::parse($d->start_date) : null;
        //         $end = $d->end_date ? Carbon::parse($d->end_date) : null;

        //         return [
        //             'id' => $d->id,
        //             'activity_name' => $d->activity_name,
        //             'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
        //             'category' => $d->category,
        //             'is_priority' => $d->is_priority,
        //             'status' => $d->status,
        //             'notes' => $d->notes,
        //             'start_date' => [
        //                 'date' => $start ? $start->format('d-m-Y') : null,
        //                 'time' => $start ? $start->format('H:i') : null,
        //             ],
        //             'end_date' => [
        //                 'date' => $end ? $end->format('d-m-Y') : null,
        //                 'time' => $end ? $end->format('H:i') : null,
        //             ],
        //             'date_range' => $start && $end
        //                 ? FormatTanggalRangeIndo::format($start, $end)
        //                 : null,
        //             'progress' => $d->progress,
        //             'attachments' => $d->attachments->map(function ($file) {
        //                 return [
        //                     'id' => $file->id,
        //                     'file_path' => asset('storage/' . $file->file_path),
        //                     'original_name' => $file->original_name,
        //                     'mime_type' => $file->mime_type,
        //                     'size' => $file->size,
        //                 ];
        //             }),
        //             'logs' => $d->logs->map(function ($log) {
        //                 return [
        //                     'id' => $log->id,
        //                     'employe_id' => $log->employe_id,
        //                     'activity_name' => trim($log->employee->first_name . ' ' . $log->employee->last_name) . ' - ' . $log->activity_name,
        //                     'notes' => $log->notes,
        //                     'created_at' => $log->created_at->format('d-m-Y H:i'),
        //                 ];
        //             }),
        //         ];
        //     });

        //     return response()->json([
        //         'status' => true,
        //         'message' => 'Berhasil mengambil data kategori tambahan',
        //         'total_task' => $reviewDailies->count(),
        //         'additional_review' => $formattedReview
        //     ]);
        // }

        public function additionalReview(Request $request)
        {
            $user = auth()->user();
            $roles = optional($user)->roles ?? [];
            $year = $request->query('year');
            $month = $request->query('month');
            $is_priority = $request->query('is_priority');
            $type = $request->query('type');

            // Ambil data employe saat ini
            $employe = Employe::where('user_id', optional($user)->id)->first();
            if (!$employe) {
                return response()->json(['status' => false, 'message' => 'Employee data not found.'], 404);
            }
            $employeId = $employe->employe_id;
            
            // Cek filter employe_id, default ke employe_id saat ini
            $filterEmployeId = $request->query('employe_id', $employeId); // Menggunakan $employeId yang sudah pasti
            
            $isManager = in_array('Manager', $roles);
            $isSupervisor = in_array('Supervisor', $roles);
            $category = $request->query('category');
            $is_priority = $request->query('is_priority');
            
            // Konversi is_priority string ke boolean/null
            $is_priority_value = null;
            if ($is_priority === 'true') {
                $is_priority_value = 1;
            } elseif ($is_priority === 'false') {
                $is_priority_value = 0;
            }

            // Jika filter category adalah 'projects', kembalikan kosong
            if ($category === 'projects') {
                return response()->json([
                    'status' => true,
                    'total' => 0,
                    'data' => []
                ], 200);
            }

            // Tentukan status review yang relevan
            $reviewStatus = null;
            if ($isManager && ($filterEmployeId === $employeId)) {
                $reviewStatus = 'review manager';
            } elseif ($isSupervisor && ($filterEmployeId === $employeId)) {
                $reviewStatus = 'review supervisor';
            }

            $listBawahan = Structure::join('users', 'users.id', '=', 'struktur_lengkap_oke.user_id')
                ->where('struktur_lengkap_oke.direct_atasan', $employeId)
                ->select('first_name', 'employe_id', 'position_name')
                ->get()
                ->map(function ($item) {
                    $profil = Profil::where('employe_id', $item->employe_id)->first();
                    $item->image = $profil
                        ? 'https://hr-api.ptpema.co.id/storage/photo/employee-photo/' . $profil->photo
                        : null;
                    return $item;
                });
            
            $idBawahan = $listBawahan->pluck('employe_id')->toArray();

            // --- Ambil daily review manager/supervisor ---
            $reviewDailies = collect();

            if ($reviewStatus) {
                $query = Daily::where('category', 'tambahan')
                    ->where('status', $reviewStatus)
                    ->whereIn('employe_id', $idBawahan)
                    ->when($is_priority_value !== null, function ($q) use ($is_priority_value) {
                        $q->where('is_priority', $is_priority_value);
                    })
                    ->when($year, function ($q) use ($year) {
                        $q->whereYear('start_date', $year);
                    })
                    ->when($month, function ($q) use ($month) {
                        $q->whereMonth('start_date', $month);
                    })
                    ->with([
                        'attachments', 
                        'employee:employe_id,first_name,last_name',
                        'logs' => function ($q) {
                            $q->orderBy('created_at', 'desc')->with('employee:employe_id,first_name,last_name');
                        }
                    ])
                    ->orderBy('start_date', 'asc')
                    ->get();
                
                $reviewDailies = $query;
            }

            // --- 1. Format Daily Activity ---
            $formattedReviewDailies = $reviewDailies->map(function ($d) {
                $start = $d->start_date ? Carbon::parse($d->start_date) : null;
                $end = $d->end_date ? Carbon::parse($d->end_date) : null;

                return [
                    'id' => $d->id,
                    'activity_name' => $d->activity_name,
                    'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                    'employe_id' => $d->employe_id,
                    'category' => $d->category,
                    'is_priority' => $d->is_priority,
                    'status' => $d->status,
                    'notes' => $d->notes,
                    'progress' => $d->progress,
                    'start_date' => [
                        'date' => $start ? $start->format('d-m-Y') : null,
                        'time' => $start ? $start->format('H:i') : null,
                    ],
                    'end_date' => [
                        'date' => $end ? $end->format('d-m-Y') : null,
                        'time' => $end ? $end->format('H:i') : null,
                    ],
                    'date_range' => (class_exists('App\Helpers\FormatTanggalRangeIndo') && $start && $end)
                        ? FormatTanggalRangeIndo::format($start, $end)
                        : null,
                    'attachments' => $d->attachments->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_path' => asset('storage/' . $file->file_path),
                            'original_name' => $file->original_name,
                            'mime_type' => $file->mime_type,
                            'size' => $file->size,
                        ];
                    }),
                    'logs' => $d->logs->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'activity_name' => trim(optional($log->employee)->first_name . ' ' . optional($log->employee)->last_name) . ' - ' . $log->activity_name,
                            'notes' => $log->notes,
                            'created_at' => optional($log->created_at)->format('d-m-Y H:i'),
                        ];
                    }),
                ];
            });

            // --- 2. Hitung Rata-rata Progress & Ambil Tanggal Hari Ini ---
            $totalProgress = $reviewDailies->sum('progress');
            $countDailies = $reviewDailies->count();

            $averageProgress = 0.00;
            if ($countDailies > 0) {
                // Hitung rata-rata dan format menjadi 2 angka desimal
                $averageProgress = (float)number_format($totalProgress / $countDailies, 2, '.', '');
            }
            
            $today = Carbon::now();

            // --- 3. Buat Struktur Task Fiktif ---
            $fictiveTask = [
                'task_id' => 'Additional-Task',
                'task_title' => 'Tugas Tambahan',
                
                // Menggunakan tanggal hari ini
                'start_date' => $today->format('Y-m-d H:i:s'), 
                'end_date' => $today->format('Y-m-d H:i:s'), 
                
                'date_range' => (class_exists('App\Helpers\FormatTanggalRangeIndo'))
                                ? FormatTanggalRangeIndo::format($today, $today)
                                : $today->format('d-m-Y'),

                'task_progress' => $averageProgress,
                'members' => collect([]),
                'daily_review' => $formattedReviewDailies,
            ];


            // --- 4. Buat Struktur Project Fiktif ---
            $formattedProjects = collect([
                [
                    'project_id' => 'project-additional',
                    'project_name' => 'Tugas Tambahan',
                    'start_date' => null,
                    'end_date' => null,
                    'date_range' => null,
                    'total_task' => $reviewDailies->count(),
                    'project_review' => collect([$fictiveTask])
                        ->filter(function ($task) {
                            return $task['daily_review']->isNotEmpty(); 
                        })->values(),
                ]
            ])
            ->filter(function ($project) {
                return $project['project_review']->isNotEmpty();
            })->values();


            // --- 5. Respon Akhir ---
            return response()->json([
                'status' => true,
                'message' => 'Berhasil mengambil data kategori tambahan',
                'total' => $formattedProjects->count(), 
                'data' => $formattedProjects, // Menggunakan kunci 'data'
            ], 200);
        }

        public function approvedAdditional(Request $request)
        {
            $user = auth()->user();
            $roles = $user->roles;
            $year = $request->query('year');
            $month = $request->query('month');
            $employe = Employe::where('user_id', $user->id)->first();
            $employeId = $employe->employe_id;
            $division = Employe::getEmployeDivision($employeId);
            $employeId = $request->query('employe_id', Employe::employeId());

            $isManager = in_array('Manager', $roles);
            $isSupervisor = in_array('Supervisor', $roles);
            $status = $request->query('sort');
            $category = $request->query('category');
            $is_priority = $request->query('is_priority');
            if($is_priority === 'true') {
                $is_priority = true;
            } elseif($is_priority === 'false') {
                $is_priority = false;
            }else{
                $is_priority = null;
            }


            if($category === 'projects') {
                return response()->json([
                    'status' => true,
                    'total' => 0,
                    'data' => []
                ], 200);
            }


            $listBawahan = Structure::join('users', 'users.id', '=', 'struktur_lengkap_oke.user_id')
                ->where('struktur_lengkap_oke.direct_atasan', $employeId)
                ->select('first_name', 'employe_id', 'position_name')
                ->get()
                ->map(function ($item) {
                    $profil = Profil::where('employe_id', $item->employe_id)->first();
                    $item->image = $profil
                        ? 'https://hr-api.ptpema.co.id/storage/photo/employee-photo/' . $profil->photo
                        : null;
                    return $item;
                });
            
            $idBawahan = $listBawahan->pluck('employe_id')->toArray();
            $myId = Employe::employeId();
            $allTargetIds = $idBawahan;
            array_push($allTargetIds, $myId);



            // --- Ambil daily tambahan user sendiri ---
            $dailies = Daily::where('category', 'tambahan')
                ->whereIn('employe_id', $allTargetIds)
                ->where('status', 'approved')
                ->when($year, function ($query) use ($year) {
                    $query->whereYear('start_date', $year);
                })
                ->when($month, function ($query) use ($month) {
                    $query->whereMonth('start_date', $month);
                })
                ->when($is_priority !== null, function ($query) use ($is_priority) {
                    $query->where('is_priority', $is_priority);
                })
                ->with(['attachments', 'logs' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                }])
                ->orderBy('start_date')
                ->get();

            // --- Ambil daily review manager/supervisor ---

            $reviewDailies = collect();

            if ($isManager && ($employeId === Employe::employeId())) {
                $reviewDailies = Daily::where('category', 'tambahan')
                    ->where('status', 'review manager')
                    ->with(['attachments', 'logs' => function ($q) {
                        $q->orderBy('created_at', 'desc');
                    }])
                    ->orderBy('start_date')
                    ->get();
            }

            if ($isSupervisor && ($employeId === Employe::employeId())) {
                $reviewDailies = Daily::where('category', 'tambahan')
                    ->where('status', 'review supervisor')
                    ->with(['attachments', 'logs' => function ($q) {
                        $q->orderBy('created_at', 'desc');
                    }])
                    ->orderBy('start_date')
                    ->get();
            }

            // --- Format hasil ---
            $formatted = $dailies->map(function ($d) {
                $start = $d->start_date ? Carbon::parse($d->start_date) : null;
                $end = $d->end_date ? Carbon::parse($d->end_date) : null;

                return [
                    'id' => $d->id,
                    'task_id' => $d->task_id,
                    'employe_id' => $d->employe_id,
                    'activity_name' => $d->activity_name,
                    'created_by' => ($d->employee) ? trim($d->employee->first_name . ' ' . $d->employee->last_name) : null,
                    'category' => $d->category,
                    'is_priority' => $d->is_priority,
                    'status' => $d->status,
                    'notes' => $d->notes,
                    'start_date' => [
                        'date' => $start ? $start->format('d-m-Y') : null,
                        'time' => $start ? $start->format('H:i') : null,
                    ],
                    'end_date' => [
                        'date' => $end ? $end->format('d-m-Y') : null,
                        'time' => $end ? $end->format('H:i') : null,
                    ],
                    'date_range' => $start && $end
                        ? FormatTanggalRangeIndo::format($start, $end)
                        : null,
                    'progress' => $d->progress,
                    'attachments' => $d->attachments->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_path' => asset('storage/' . $file->file_path),
                            'original_name' => $file->original_name,
                            'mime_type' => $file->mime_type,
                            'size' => $file->size,
                        ];
                    }),
                    'logs' => $d->logs->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'employe_id' => $log->employe_id,
                            'activity_name' => trim($log->employee->first_name . ' ' . $log->employee->last_name) . ' - ' . $log->activity_name,
                            'notes' => $log->notes,
                            'created_at' => $log->created_at->format('d-m-Y H:i'),
                        ];
                    }),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Berhasil mengambil data kategori tambahan',
                'total' => $dailies->count(),
                'data' => [
                    'category' => 'tambahan',
                    'task_progress' => round(
                                            $dailies->whereIn('employe_id', $allTargetIds)
                                                    ->where('category', 'tambahan')
                                                    ->where('status', '!=','cancelled')
                                                    ->avg('progress'), 
                                            2 // Maksimal 2 angka di belakang koma
                                        ) ?? 0,
                    // 'task_progress' => $dailies->where('employe_id', $employeId)->where('category', 'tambahan')->where('status', '!=','cancelled')->avg('progress') ?? 0,
                    'date_range' => now()->format('d F Y'),
                    'daily' => $formatted,
                ]
            ]);
        }

        
        public function listAssignments()
        {
            $employeId = Employe::employeId();

            $saya = Structure::join('users', 'users.id', '=', 'struktur_lengkap_oke.user_id')
                ->where('struktur_lengkap_oke.employe_id', $employeId)
                ->select('first_name', 'employe_id', 'position_name')
                ->first();
            if ($saya) {
                $profil = Profil::where('employe_id', $saya->employe_id)->first();
                $saya->image = $profil
                    ? 'https://hr-api.ptpema.co.id/storage/photo/employee-photo/' . $profil->photo
                    : null;
            }

            $listBawahan = Structure::join('users', 'users.id', '=', 'struktur_lengkap_oke.user_id')
                ->where('struktur_lengkap_oke.direct_atasan', $employeId)
                ->select('first_name', 'employe_id', 'position_name')
                ->get()
                ->map(function ($item) {
                    $profil = Profil::where('employe_id', $item->employe_id)->first();
                    $item->image = $profil
                        ? 'https://hr-api.ptpema.co.id/storage/photo/employee-photo/' . $profil->photo
                        : null;
                    return $item;
                });

            return response()->json([
                'status' => true,
                'message' => 'Successfully get data',
                'saya' => $saya,
                'total_bawahan' => $listBawahan->count(),
                'list_bawahan' => $listBawahan
            ]);
        }

    }
