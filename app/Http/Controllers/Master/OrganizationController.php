<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Organization;

class OrganizationController extends Controller
{
    public function insertCode()
    {
        $data = Organization::all();

        // generate 4 digit random number
        $savedCode = [];

        for ($i=0; $i < count($data); $i++) { 
            $code = mt_rand(1000, 9999);

            if(!in_array($code, $savedCode)){
                
                $theCode = 'ORG'.$code;
            }else{
                $otherCode = mt_rand(1000, 9999);
                $theCode = 'ORG'.$otherCode;
            }

            Organization::where('board_id', $data[$i]['board_id'])
                                ->update([
                                    'organization_code' => $theCode
                                ]);

            array_push($savedCode, $theCode);
        }

        return response()->json([
            'data' => $data
        ], 200);
    }
}
