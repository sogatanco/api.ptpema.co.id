<?php

namespace App\Modules\Daily\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Daily\Requests\ChangeProgressRequest;
use App\Modules\Daily\Services\ChangeProgressService;

class ChangeProgressController extends Controller
{
    public function __construct(
        protected ChangeProgressService $service
    ) {}

    public function changeProgress(ChangeProgressRequest $request)
    {
        try {
            $result = $this->service->handle($request->validated());
            return ApiResponse::success($result, 'Berhasil mengubah progress');
        } catch (\Throwable $th) {
            return ApiResponse::error('Gagal mengubah progress', $th->getMessage(), 500);
        }
    }
}
