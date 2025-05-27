<?php

namespace App\Http\Controllers\Wa;

use App\Http\Controllers\Controller;
use App\Models\Wa\SendWa;


class SendMessageController extends Controller
{
    public function getFirst()
    {
        $data = SendWa::whereNull('s_wa')->first();
        if ($data && isset($data->pn)) {
            $data->number =$data->pn;
        }
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
