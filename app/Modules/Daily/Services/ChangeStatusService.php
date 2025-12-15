<?php

namespace App\Modules\Daily\Services;

use App\Modules\Daily\Repositories\DailyRepository;
use Illuminate\Support\Facades\DB;
use App\Models\Employe;

class ChangeStatusService
{
    public function __construct(
        protected DailyRepository $repo
    ) {}

    public function handle(array $data): array
    {
        $updated = [];

        // return $data;

        DB::transaction(function () use ($data, &$updated) {
            foreach ($data['dailies'] as $item) {
                // cek atasan berdasarkan employe_id
                $atasan = DB::table('atasan_terkait')
                    ->where('employe_id', Employe::employeId())
                    ->first();

                // $status = null;
                // if($item['status'] === 'cancelled'){
                //     $status = 'cancelled';
                // }else{
                //     // Menentukan Atasan Terkait
                //     if ($atasan && $atasan->supervisor_terkait) {
                //         $status = 'review supervisor';
                //     } 
                //     if ($atasan && $atasan->manager_terkait) {
                //         $status = 'review manager';
                //     }
                // }

                if ($item['status'] === 'review')
                {
                    // Menentukan Atasan Terkait
                    if ($atasan && $atasan->supervisor_terkait) {
                        $status = 'review supervisor';
                    } 
                    if ($atasan && $atasan->manager_terkait) {
                        $status = 'review manager';
                    }
                }else{
                    $status = $item['status'];
                }

    
      

                $task = $this->repo->updateProgress($item['id'], [
                    'status' => $status
                ]);

                if($task){
                    $this->repo->logAction($task->id, 'Changed status to ' . $status);
                }

                $updated[] = $task;
            }
        });

        return $updated;
    }
}
