<?php

namespace App\Models\Daily;

use App\Models\Employe;
use App\Models\Tasks\Task;
use App\Models\Daily\DailyAttachment;
use App\Models\Daily\DailyLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Daily extends Model
{
    use HasFactory;

    protected $table = 'daily';

    protected $fillable = [
        'employe_id',
        'task_id',
        'activity_name',
        'category',
        'progress',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employe::class, 'employe_id', 'employe_id');
    }

    public function attachments()
    {
        return $this->hasMany(DailyAttachment::class, 'daily_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(DailyLog::class, 'daily_id', 'id');
    }

    protected static function booted()
    {
        static::created(function ($daily) {
            $daily->updateTaskProgress();
        });

        static::updated(function ($daily) {
            $daily->updateTaskProgress();
        });

        static::deleted(function ($daily) {
            $daily->updateTaskProgress(true);
        });
    }

    public function updateTaskProgress($isDelete = false)
    {
        if (!$this->task_id) {
            return;
        }

        $taskId = $this->task_id;

        // Ambil semua daily dengan task_id sama (kecuali yang canceled/null)
        $tasks = self::where('task_id', $taskId)
            ->whereIn('status', [
                'in progress',
                'review supervisor',
                'review manager',
                'revised',
                'approved',
            ])
            ->get(['progress', 'status']);

        if ($tasks->isEmpty()) {
            // Tidak ada data, set ke 0
            \App\Models\Tasks\Task::where('task_id', $taskId)
                ->update(['task_progress' => 0]);
            return;
        }

        // Hitung jumlah task approved dan total task
        $approvedCount = $tasks->where('status', 'approved')->count();
        $totalCount = $tasks->count();

        // Jika semua sudah approved → progress 100%
        if ($approvedCount === $totalCount) {
            $finalProgress = 100;
        } else {
            // Task approved dianggap 100%, lainnya ambil dari kolom progress
            $sumProgress = $tasks->sum(function ($t) {
                return $t->status === 'approved' ? 100 : $t->progress;
            });

            // Rata-rata dari semua progress
            $finalProgress = $sumProgress / $totalCount;
        }

        // Update ke tabel project_task
        \App\Models\Tasks\Task::where('task_id', $taskId)
            ->update(['task_progress' => round($finalProgress, 2)]);
    }

    public static function recalculateTaskProgress($taskId)
    {
        $tasks = self::where('task_id', $taskId)
            ->whereIn('status', [
                'in progress',
                'review supervisor',
                'review manager',
                'revised',
                'approved',
            ])
            ->get(['progress', 'status']);

        if ($tasks->isEmpty()) {
            \App\Models\Tasks\Task::where('task_id', $taskId)
                ->update(['task_progress' => 0]);
            return;
        }

        $approvedCount = $tasks->where('status', 'approved')->count();
        $totalCount = $tasks->count();

        $finalProgress = ($approvedCount === $totalCount)
            ? 100
            : $tasks->sum(fn($t) => $t->status === 'approved' ? 100 : $t->progress) / $totalCount;

        \App\Models\Tasks\Task::where('task_id', $taskId)
            ->update(['task_progress' => round($finalProgress, 2)]);
    }




}
