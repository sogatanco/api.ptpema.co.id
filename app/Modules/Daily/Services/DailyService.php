<?php

namespace App\Modules\Daily\Services;

use App\Models\Daily\Daily;
use App\Models\Employe;
use App\Modules\Daily\Repositories\DailyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DailyService
{
    protected DailyRepository $dailyRepo;

    public function __construct(DailyRepository $dailyRepo)
    {
        $this->dailyRepo = $dailyRepo;
    }

    public function store(array $data): bool
    {
        try {
            return DB::transaction(function () use ($data) {
                $employeId = Employe::employeId();

                $dailies = collect($data['dailies'])->map(function ($item) use ($employeId) {
                    return [
                        'task_id' => $item['task_id'],
                        'employe_id' => $employeId,
                        'activity_name' => $item['activity_name'],
                        'category' => $item['category'],
                        'progress' => 0,
                        'start_date' => $item['start_date'],
                        'end_date' => $item['end_date'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                return $this->dailyRepo->bulkInsert($dailies);
            });
        } catch (\Exception $e) {
            // Log::error($e->getMessage());
            return false;
        }
    }

    public function update(array $data): ?Daily
    {
        try {
            $daily = $this->dailyRepo->update($data['id'], $data);

            if (!$daily) {
                throw new \Exception("Data tidak ditemukan.");
            }

            return $daily;
        } catch (\Exception $e) {
            // log error
            throw $e;
        }
    }

    public function deleteMany(array $ids): void
    {
        try {
            $this->dailyRepo->deleteByIds($ids);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /*
    public function getByCategory(string $category): array
    {
        try {
            $dailies = $this->dailyRepo->getByCategory($category);

            return [
                'category' => $category,
                'dailies' => $dailies
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
    */

    public function getByCategory(string $category): array
    {
        try {
            $dailies = $this->dailyRepo->getByCategory($category);
            $user = auth()->user();
            $role = $user->role->name ?? null; // pastikan kamu punya relasi role

            $taskReview = $dailies->filter(function ($daily) use ($role) {
                return ($daily->status === 'review supervisor' && $role === 'supervisor')
                    || ($daily->status === 'review manager' && $role === 'manager');
            })->values();

            return [
                'category' => $category,
                'task_progress' => $dailies->avg('progress') ?? 0,
                'date_range' => optional($dailies->first())->date_range ?? null,
                'dailies' => $dailies,
                'task_review' => $taskReview ?? [],
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
