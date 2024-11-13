<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;

class PositionController extends Controller
{
    public function insertCode()
    {
        $data = Position::all();

        // generate 4 digit random number
        
        for ($i=0; $i < count($data); $i++) { 
            $code = mt_rand(1000, 9999);

            Position::where('position_id', $data[$i]['position_id'])
                                ->update([
                                    'position_code' => 'PO'.$code.'S'
                                ]);
        }

        return response()->json([
            'data' => $data
        ], 200);
    }
}
