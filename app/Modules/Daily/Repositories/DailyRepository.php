<?php

namespace App\Modules\Daily\Repositories;

use App\Models\Daily\Daily;
use App\Models\Employe;
use Illuminate\Database\Eloquent\Collection;

class DailyRepository
{
    public function create(array $data): bool
    {
        return Daily::create($data) ? true : false;
    }

    public function bulkInsert(array $dailies): bool
    {
        return Daily::insert($dailies);
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

            return $daily->fresh();
        } catch (\Exception $e) {
            // log error
            throw $e;
        }
    }

    public function deleteByIds(array $ids): void
    {
        try {
            Daily::whereIn('id', $ids)->delete();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getByCategory(string $category): Collection
    {
        return Daily::where('category', $category)
            ->where('employe_id', Employe::employeId())
            ->orderBy('start_date')
            ->get();
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
}
