<?php

namespace App\Modules\Daily\Services;

use App\Modules\Daily\Repositories\DailyRepository;
use Illuminate\Support\Facades\DB;
use App\Models\Employe;

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
                    // $updateData['status'] = 'review'; // pastikan kolom status ada
                    $employeId = Employe::employeId();
                    $atasan = DB::table('atasan_terkait')
                            ->where('employe_id', $employeId)
                            ->first();
                    if($atasan) {
                        if(!is_null($atasan->supervisor_terkait)){
                            $updateData['status'] = 'review supervisor';
                        } else if(!is_null($atasan->manager_terkait)){
                            $updateData['status'] = 'review manager';
                        }
                    }

                }

                $updated = $this->repo->updateProgress($daily['id'], $updateData);
                $result[] = $updated; // bisa gunakan DailyResource nanti

                if($daily['progress'] == 100 && isset($daily['change_to_review']) && $daily['change_to_review'])
                {
                    $this->repo->logAction($updated->id, 'Changed status to ' . $updateData['status']);
                }
            }

            return $result;
        });
    }
}
