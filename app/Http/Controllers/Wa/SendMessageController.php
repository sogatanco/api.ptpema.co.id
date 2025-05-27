<?php

namespace App\Http\Controllers\Wa;

use App\Http\Controllers\Controller;
use App\Models\Wa\SendWa;


class SendMessageController extends Controller
{
    public function getFirst()
    {
        $data = SendWa::first();
        if ($data && isset($data->pn)) {
            $data->pn = (int) $data->pn;
        }
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
