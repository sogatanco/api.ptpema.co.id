<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BoardOrganization;
use Illuminate\Http\Exceptions\HttpResponseException;

class BoardOrganizationController extends Controller
{
    
    public function store(Request $request)
    {
        $data = BoardOrganization::create([
            'company_id' => 1,
            'board_code' => $request->board_code,
            'board_name' => $request->board_name,
        ]);

        if(!$data){
            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Failed to create board'
            ], 500));
        }

        return response()->json([
            'status' => true,
            'message' => 'Successfully created board',
        ], 200);
    }

    public function update(Request $request)
    {
        $data = BoardOrganization::where('board_code', $request->board_code)->first();

        if(!$data){

           BoardOrganization::create([
                'company_id' => 1,
               'board_code' => $request->board_code,
               'board_name' => $request->board_name,
           ]);

        } else {

            $isSaved = BoardOrganization::where('board_code', $request->board_code)
                                ->update([
                                    'board_name' => $request->board_name,
                                ]);
    
            if(!$isSaved){
               throw new HttpResponseException(response([
                   'status' => false,
                   'message' => 'Failed to update board'
               ], 500));
            }

        }

        return response()->json([
            'status' => true,
            'message' => 'Successfully updated board',
        ], 200);
    }

    public function delete(Request $request)
    {
        BoardOrganization::where('board_code', $request->board_code)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Successfully deleted board',
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
