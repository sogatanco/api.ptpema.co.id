<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\Organization;
use Illuminate\Http\Exceptions\HttpResponseException;


class PositionController extends Controller
{
    public function store(Request $request)
    {
        $organization = Organization::where('organization_code', $request->organization_code)->first();

        $parent = Position::where('position_code', $request->parent_code)->first();

        $data = Position::create([
            'organization_id' => $organization->organization_id,
            'parent_id' => $parent->position_id,
            'position_code' => $request->position_code,
            'position_name' => $request->position_name,
            'id_base' => 9
        ]);

        if(!$data){
            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Failed to create position'
            ], 500));
        }

        return response()->json([
            'status' => true,
            'message' => 'Successfully created position',
        ], 200);
    }

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

    public function allPosition()
    {
        $data = Position::all();

        return response()->json([
            'data' => $data
        ], 200);
    }
}
