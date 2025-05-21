<?php

namespace App\Modules\Daily\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Daily\Requests\DeleteDailyRequest;
use App\Modules\Daily\Requests\UpdateDailyRequest;
use App\Modules\Daily\Requests\StoreDailyRequest;
use App\Modules\Daily\Resources\DailyByCategoryResource;
use App\Modules\Daily\Resources\DailyResource;
use App\Modules\Daily\Services\DailyService;

class DailyController extends Controller
{

    protected DailyService $dailyService;

    public function __construct(DailyService $dailyService)
    {
        $this->dailyService = $dailyService;
    }

    public function store(StoreDailyRequest $request)
    {
        $isSuccess = $this->dailyService->store($request->validated());

        if (!$isSuccess) {
           return ApiResponse::error('Gagal menyimpan data', 500);
        }

        return ApiResponse::success(null, 'Berhasil menyimpan data', 200);
    }

    public function update(UpdateDailyRequest $request)
    {
        try {
            $updated = $this->dailyService->update($request->validated());
            return apiResponse::success(new DailyResource($updated), 'Berhasil memperbarui data', 200);
        } catch (\Throwable $th) {
            return apiResponse::error('Terjadi kesalahan ', $th->getMessage(), 500);
        }
    }

    public function destroy(DeleteDailyRequest $request)
    {
        try {
            $this->dailyService->deleteMany($request->validated()['dailies']);
            return ApiResponse::success(null, 'Berhasil menghapus data');
        } catch (\Throwable $th) {
            return ApiResponse::error('Gagal menghapus data', $th->getMessage(), 500);
        }
    }

    public function getByCategory($category)
    {
        try {
            $dailies = $this->dailyService->getByCategory($category);
            return ApiResponse::success(new DailyByCategoryResource($dailies), "Berhasil mengambil data kategori {$category}");
        } catch (\Throwable $th) {
            return ApiResponse::error('Gagal mengambil data', $th->getMessage(), 500);
        }
    }
}
