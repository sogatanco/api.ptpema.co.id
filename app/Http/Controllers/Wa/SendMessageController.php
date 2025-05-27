<?php

namespace App\Http\Controllers\Wa;

use App\Http\Controllers\Controller;
use App\Models\Wa\SendWa;
use App\Models\Wa\Notif;
use Carbon\Carbon;

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

    public function setNotifSwaNow($id)
    {
        $notif = Notif::find($id);
        if ($notif) {
            $notif->s_wa = Carbon::now();
            $notif->save();
            return response()->json([
                'success' => true,
                'message' => 's_wa updated',
                'data' => $notif
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'Notif not found'
        ], 404);
    }
}
