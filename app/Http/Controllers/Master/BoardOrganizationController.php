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

        // generate 4 digit random number
        for ($i=0; $i < count($data); $i++) { 
            $code = mt_rand(1000, 9999);
            $data[$i]['board_code'] = 'BOA'.$code;

            $new = BoardOrganization::find($data[$i]['id']);
            $new->board_code = 'BOA'.$code;
            $new->save();
        }

        return response()->json([
            'data' => $data
        ], 200);
    }
}
