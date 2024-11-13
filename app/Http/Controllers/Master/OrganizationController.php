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
        
        for ($i=0; $i < count($data); $i++) { 
            $code = mt_rand(1000, 9999);

            Organization::where('organization_id', $data[$i]['organization_id'])
                                ->update([
                                    'organization_code' => 'ORG'.$code
                                ]);
        }

        return response()->json([
            'data' => $data
        ], 200);
    }
}
