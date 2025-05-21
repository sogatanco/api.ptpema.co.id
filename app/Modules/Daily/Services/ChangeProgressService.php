<?php

namespace App\Modules\Daily\Services;

use App\Modules\Daily\Repositories\DailyRepository;
use Illuminate\Support\Facades\DB;

class ChangeProgressService
{
    public function __construct(
        protected DailyRepository $repo
    ) {}

    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $result = [];

            foreach ($data['dailies'] as $daily) {
                $updateData = ['progress' => $daily['progress']];

                if (
                    $daily['progress'] == 100 &&
                    isset($daily['change_to_review']) &&
                    $daily['change_to_review']
                ) {
                    $updateData['status'] = 'review'; // pastikan kolom status ada
                }

                $updated = $this->repo->updateProgress($daily['id'], $updateData);
                $result[] = $updated; // bisa gunakan DailyResource nanti
            }

            return $result;
        });
    }
}
