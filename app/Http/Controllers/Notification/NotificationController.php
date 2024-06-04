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
            if($employe->employe_id !== $recipients[$r]->employe_id){
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
    }

    public function get(){

        $employeId = Employe::where('user_id', Auth::user()->id)->first()->employe_id;

        $data = Notification::select(
                                'notifications.id', 
                                'notifications.entity_id', 
                                'notifications.created_at', 
                                'notification_entity_type.type',
                                'notification_entity_type.message', 
                                'notification_entity_type.url',
                                'notification_entity.entity',
                                'employees.first_name AS actor',
                                'positions.position_name AS position'
                            )
                            ->where(['recipient' => $employeId, 'status' => 0])
                            ->join('notification_entity_type', 'notification_entity_type.id', '=', 'notifications.entity_type_id')
                            ->join('notification_entity', 'notification_entity.id', '=', 'notification_entity_type.entity_id')
                            ->join('employees', 'employees.employe_id', '=', 'notifications.actor')
                            ->join('positions', 'positions.position_id', '=', 'employees.position_id')
                            ->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }
}
