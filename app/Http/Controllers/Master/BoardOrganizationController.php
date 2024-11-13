<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BoardOrganization;

class BoardOrganizationController extends Controller
{
    public function insertCode()
    {
        $data = BoardOrganization::all();

        return response()->json([
            'data' => $data
        ], 200);
    }
}
