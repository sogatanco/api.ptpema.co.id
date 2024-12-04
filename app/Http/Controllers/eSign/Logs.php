<?php

namespace App\Http\Controllers\eSign;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Structure;
use App\Models\ESign\Log;

class Logs extends Controller
{
    public function getLogs($id_doc){
        $data=Log::where('id_document', $id_doc)->first();
        $data['first_name']=Structure::where('employe_id', $data->employe_id)->first('first_name')->first_name;
        return new PostResource(true, 'data logs', $data);
    }
}
