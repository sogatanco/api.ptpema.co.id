<?php

namespace App\Http\Controllers\Wa;

use App\Http\Controllers\Controller;
use App\Models\Wa\SendWa;
use App\Models\Wa\Notif;
use Carbon\Carbon;

class SendMessageController extends Controller
{
    private function checkBearerToken($request)
    {
        $token = $request->bearerToken();
        $expected ='6e766aa21ef5173e73d602767850bbe1f2c51af2';
        if (!$token || $token !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        return null;
    }

    public function getFirst(\Illuminate\Http\Request $request)
    {
        if ($resp = $this->checkBearerToken($request)) return $resp;

        $data = SendWa::whereNull('s_wa')->first();
        if ($data && isset($data->pn)) {
            $data->number = $data->pn;
        }
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function setNotifSwaNow(\Illuminate\Http\Request $request, $id)
    {
        if ($resp = $this->checkBearerToken($request)) return $resp;

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
