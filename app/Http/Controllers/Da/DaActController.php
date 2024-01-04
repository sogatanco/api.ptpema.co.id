<?php

namespace App\Http\Controllers\Da;

use App\Http\Controllers\Controller;
use App\Models\DailyAct\DaActivit;

class DaActController extends Controller
{
    function index()
    {
        $list = DaActivit::where('activity', 'not like', '%#%')->where('status', 'approve')->get();
        $list = $list->groupBy('activity');
        $listKey = array_keys($list->toArray());
        $data = [];
        for ($i = 0; $i < count($listKey); $i++) {
            $data[$i] = array(
                'activity' => $listKey[$i],
                'id' => $i + 1, 
                'category' => DaActivit::where('activity', $listKey[$i])->get()->first()->category,
                'poin' => DaActivit::where('activity', $listKey[$i])->get()->first()->poin,
            );
        }
        return response()->json([
            "status" => true,
            "data" => $data,
        ], 200);
    }
}
