<?php

namespace App\Modules\Daily\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Daily\Requests\ChangeStatusRequest;
use App\Modules\Daily\Services\ChangeStatusService;

class ChangeStatusController extends Controller
{
    public function __construct(
        protected ChangeStatusService $service
    ) {}

    public function __invoke(ChangeStatusRequest $request)
    {
        try {
            $result = $this->service->handle($request->validated());
            return ApiResponse::success($result, 'Status berhasil diubah untuk beberapa task');
        } catch (\Throwable $th) {
            return ApiResponse::error('Gagal mengubah status', $th->getMessage(), 500);
        }
    }
}
