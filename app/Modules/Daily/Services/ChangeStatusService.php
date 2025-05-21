<?php

namespace App\Modules\Daily\Services;

use App\Modules\Daily\Repositories\DailyRepository;
use Illuminate\Support\Facades\DB;

class ChangeStatusService
{
    public function __construct(
        protected DailyRepository $repo
    ) {}

    public function handle(array $data): array
    {
        $updated = [];

        DB::transaction(function () use ($data, &$updated) {
            foreach ($data['dailies'] as $item) {
                $task = $this->repo->updateProgress($item['id'], ['status' => $item['status']]);
                $updated[] = $task;
            }
        });

        return $updated;
    }
}
