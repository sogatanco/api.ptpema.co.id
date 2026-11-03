<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\BoardOrganization;

// Data board dibaca dari HR (view apam.board_organizations = HR board_organizations); tidak ada lagi tabel board di apam.
// Endpoint tulis dipertahankan agar otomasi HR tidak gagal, tetapi tidak mengubah apa pun.
class BoardOrganizationController extends Controller
{
    private function managedByHr()
    {
        return response()->json([
            'status' => true,
            'message' => 'Data board dikelola di HR; tidak ada perubahan di apam.',
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

    public function allBoard()
    {
        return response()->json([
            'data' => BoardOrganization::all()
        ], 200);
    }
}
