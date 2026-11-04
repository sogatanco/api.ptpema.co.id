<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Hr\Position as HrPosition;

// Jabatan dibaca dari HR (ptpemaco_hr_production.positions); tidak ada lagi tabel jabatan di apam.
// Endpoint tulis dipertahankan agar otomasi HR tidak gagal, tetapi tidak mengubah apa pun.
class PositionController extends Controller
{
    private function managedByHr()
    {
        return response()->json([
            'status' => true,
            'message' => 'Data jabatan dikelola di HR; tidak ada perubahan di apam.',
        ], 200);
    }

    public function store()
    {
        return $this->managedByHr();
    }

    public function update()
    {
        return $this->managedByHr();
    }

    public function delete()
    {
        return $this->managedByHr();
    }

    public function insertCode()
    {
        return $this->managedByHr();
    }

    public function allPosition()
    {
        // bentuk sama dengan tabel positions lama: position_id kini id jabatan HR
        $data = HrPosition::selectRaw('id as position_id, division_id as organization_id, position_name, position_code, position_level, parent_id, created_at, updated_at')->get();

        return response()->json([
            'data' => $data
        ], 200);
    }
}
