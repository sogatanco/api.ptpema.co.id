<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Notification\NotificationEntityType;
use App\Models\Notification\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{

    public static function new($type, $recipients, $entityId)
    {
        $userId = Auth::user()->id;
        $employe = Employe::where('user_id', $userId)->first();

        // choose entity
        $entityTypeId = NotificationEntityType::where('type', $type)->first()->id;

        // notification data
        for ($r=0; $r < count($recipients); $r++) { 
            $data = [
                'actor' => $employe->employe_id,
                'recipient' => $recipients[$r]->employe_id,
                'entity_type_id' => $entityTypeId,
                'entity_id' => $entityId,
            ];

            $newNotification = new Notification($data);
            $newNotification->save();
        }
    }

    public function get(){

        $employeId = Employe::where('user_id', Auth::user()->id)->first()->employe_id;

        $data = Notification::where(['recipient' => $employeId, 'status' => 0])
                            ->join('notification_entity_type', 'notification_entity_type.id', '=', 'notification.entity_type_id')
                            ->join('notification_entity', 'notification_entity.id', '=', 'notification_entity_type.entity_id')
                            ->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }
}
