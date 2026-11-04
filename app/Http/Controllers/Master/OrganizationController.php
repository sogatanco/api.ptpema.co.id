<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Organization;

// Data organisasi/divisi dibaca dari HR (view apam.organizations = HR divisions); tidak ada lagi tabel organisasi di apam.
// Endpoint tulis dipertahankan agar otomasi HR tidak gagal, tetapi tidak mengubah apa pun.
class OrganizationController extends Controller
{
    private function managedByHr()
    {
        return response()->json([
            'status' => true,
            'message' => 'Data organisasi dikelola di HR; tidak ada perubahan di apam.',
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

    public function allOrganization()
    {
        return response()->json([
            'data' => Organization::all()
        ], 200);
    }
}
