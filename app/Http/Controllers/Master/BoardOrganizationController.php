<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BoardOrganization;
use Illuminate\Http\Exceptions\HttpResponseException;

class BoardOrganizationController extends Controller
{
    public function update(Request $request, $code)
    {
        $isUpdated = BoardOrganization::where('board_code', $code)
                            ->update([
                                'board_name' => $request->board_name
                            ]);
        if(!$isUpdated){
           throw new HttpResponseException(response([
               'message' => 'Failed to update board'
           ], 500));
        }          
        
        return response()->json([
            'message' => 'Successfully updated board'
        ], 200);
    }

    public function insertCode()
    {
        $data = BoardOrganization::all();

        // generate 4 digit random number
        for ($i=0; $i < count($data); $i++) { 
            $code = mt_rand(1000, 9999);
            $data[$i]['board_code'] = 'BOA'.$code;

            BoardOrganization::where('board_id', $data[$i]['board_id'])
                                ->update([
                                    'board_code' => 'BOA'.$code
                                ]);
        }

        return response()->json([
            'data' => $data
        ], 200);
    }

    public function allBoard()
    {
        $data = BoardOrganization::all();

        return response()->json([
            'data' => $data
        ], 200);
    }
}
