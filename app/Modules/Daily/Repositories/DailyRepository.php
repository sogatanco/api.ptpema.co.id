<?php

namespace App\Modules\Daily\Repositories;

use App\Models\Daily\Daily;
use App\Models\Employe;
use App\Models\Daily\DailyLog;
use App\Models\Projects\Project;
use Illuminate\Database\Eloquent\Collection;

class DailyRepository
{
    public function create(array $data): bool
    {
        // return Daily::create($data) ? true : false;
        $daily = Daily::create($data);

        if ($daily) {
            $this->logAction($daily->id, 'created daily');
            return true;
        }

        return false;
    }

    public function bulkInsert(array $dailies): bool
    {
        foreach ($dailies as $dailyData) {
            $daily = Daily::create($dailyData);
            $this->logAction($daily->id, 'created daily');
        }

        return true;
    }

    public function update(int $id, array $data): ?Daily
    {
        try {
            $daily = Daily::find($id);

            if (!$daily) {
                return null;
            }

            $daily->update([
                'activity_name' => $data['activity_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);

            if ($daily->wasChanged()) {
                $this->logAction($daily->id, 'updated daily');
            }

            return $daily->fresh();
        } catch (\Exception $e) {
            // log error
            throw $e;
        }
    }

    /*
    public function deleteByIds(array $ids): void
    {
        try {
            Daily::whereIn('id', $ids)->delete();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    */

    public function deleteByIds(array $ids): void
    {
        try {
            $taskIds = Daily::whereIn('id', $ids)
                ->pluck('task_id')
                ->unique();

            Daily::whereIn('id', $ids)->delete();

            foreach ($taskIds as $taskId) {
                Daily::recalculateTaskProgress($taskId);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }


    /*
    public function getByCategory(string $category): Collection
    {
        return Daily::where('category', $category)
            ->where('employe_id', Employe::employeId())
            ->orderBy('start_date')
            ->get();
    }
    */

   public function getByCategory(string $category): Collection
    {
        $user = auth()->user();
        $roles = $user->roles;
        $employe = Employe::where('user_id', $user->id)->first();
        if (!$employe) {
            return collect();
        }

        $employeId = $employe->employe_id;
        $division = Employe::getEmployeDivision($employeId);

        $isManager = in_array('Manager', $roles);
        $isSupervisor = in_array('Supervisor', $roles);

        // default: ambil miliknya sendiri
        $dailies = Daily::where('category', $category)
            ->where('employe_id', $employeId)
            ->orderBy('start_date')
            ->get();
        
        $reviewProjects = collect();

        if ($isSupervisor) {
            $reviewProjects = Daily::where('category', $category)
                ->where('status', 'review supervisor')
                ->whereHas('task', function ($query) use ($division) {
                    $query->where('division', $division->organization_id);
                })
                ->orderBy('start_date')
                ->get();
        }
if ($isManager) {
    // Log info user dan divisi
    \Log::info('Masuk ke blok manager', [
        'user' => $user,
        'division_organization_id' => $division->organization_id ?? null,
    ]);

    // Query data daily untuk manager
    $managerProjects = Daily::where('category', $category)
        ->where('status', 'review manager')
        ->whereHas('task', function ($query) use ($division) {
            // Pastikan kolomnya 'division', bukan 'division_id'
            $query->where('division', $division->organization_id);
        })
        ->orderBy('start_date')
        ->get();

    // Log hasil query
    \Log::info('managerProjects detail', [
        'count' => $managerProjects->count(),
        'data' => $managerProjects->map(fn($item) => [
            'id' => $item->id,
            'task_id' => $item->task_id,
            'employe_id' => $item->employe_id,
            'status' => $item->status,
            'category' => $item->category,
            'start_date' => $item->start_date,
        ])->toArray(),
    ]);

    // Gabungkan hasil dengan daily milik sendiri
    $reviewProjects = $reviewProjects->merge($managerProjects);
}



        return $dailies->merge($reviewProjects)->sortBy('start_date')->values();
    }







    public function updateProgress(int $id, array $data): ?Daily
    {
        try {
            $daily = Daily::findOrFail($id);
            $daily->update($data);
            return $daily->fresh();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function logAction($dailyId, $activityName, $notes = null)
    {
        DailyLog::create([
            'daily_id'      => $dailyId,
            'employe_id'   => Employe::employeId(),
            'activity_name' => $activityName,
            'notes'         => $notes,
        ]);
    }
}
