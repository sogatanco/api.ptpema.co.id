<?php

namespace App\Modules\Projects\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Projects\Services\ProjectService;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $service
    ) {}

    public function listProjectByDivision()
    {
        try {
            $data = $this->service->listProjectByDivision();
            return ApiResponse::success($data, 'Berhasil mengambil list Poject');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal mengambil list Poject', $e->getMessage());
        }
    }
}
