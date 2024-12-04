<?php

namespace App\Http\Controllers\eSign;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\ESign\VerifStep;
use App\Models\Structure;
use App\Models\ESign\Log;


class Logs extends Controller
{
    public function getLogs($id_doc)
    {
        $data = Log::where('id_document', $id_doc)->get();
        foreach ($data as $item) {
            $item['first_name'] = Employe::where('employe_id', $item->employe_id)->first()->first_name;
            $item['position_name'] = $item->id_current_position;

        }
        return new PostResource(true, 'data logs', $data);
    }

    public function getApproval($id_doc)
    {
        $data = VerifStep::where('id_doc', $id_doc)->get();
        foreach ($data as $item) {
            $item['first_name'] = Employe::where('employe_id', $item->employe_id)->first()->first_name;
            $item['position_name'] = $item->id_current_position;
        }
        return new PostResource(true,'data approval', $data);
    }
}
