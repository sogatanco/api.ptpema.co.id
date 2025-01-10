<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\Organization;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\StructureMaster;

class PositionController extends Controller
{
    public function store(Request $request)
    {
        $organization = Organization::where('organization_code', $request->organization_code)->first();

        $parent = Position::where('position_code', $request->parent_code)->first();

        $positionSaved = Position::create([
            'organization_id' => $organization->organization_id,
            'position_code' => $request->position_code,
            'position_name' => $request->position_name,
            'id_base' => null
        ]);

        // return response()->json([
        //     'status' => true,
        //     'posisi_baru' => $positionSaved,
        //     'id1' => $positionSaved->id,
        //     'id2' => $positionSaved['id'],
        //     'parent' => $parent
        // ], 200);

        if(!$positionSaved){
            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Failed to create position'
            ], 500));
        }

        // save to structure master
        $masterSaved = StructureMaster::create([
                        'position_id' => $positionSaved['id'],
                        'direct_supervisor' => $parent->position_id
                    ]);

        if(!$masterSaved){

            // delete new position saved
            Position::where('position_id', $positionSaved->position_id)->delete();

            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Failed to create structure master'
            ], 500));
        }

        return response()->json([
            'status' => true,
            'message' => 'Successfully created position',
        ], 200);
    }

    public function update(Request $request)
    {

        $organization = Organization::where('organization_code', $request->organization_code)->first();

        $position = Position::where('position_code', $request->position_code)->first();

        if(!$position){

            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Position not found',
                "request" => $request->all()
            ], 404));

        }

        $isUpdated = Position::where('position_id', $position->position_id)
                                ->update([
                                    'organization_id' => $organization->organization_id,
                                    'position_name' => $request->position_name,
                                    'id_base' => null
                                ]);

        if($isUpdated){
            // search parent
            $parent = Position::where('position_code', $request->parent_code)->first();

            // update structure master
            StructureMaster::where('position_id', $position->position_id)->update([
                'direct_supervisor' => $parent->position_id,
            ]);

        } else {
            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Failed to update position'
            ], 500));
        }

        return response()->json([
            'status' => true,
            'message' => 'Successfully updated position',
        ], 200);
    }

    public function delete(Request $request)
    {
        Position::where('position_code', $request->position_code)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Successfully deleted position',
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
