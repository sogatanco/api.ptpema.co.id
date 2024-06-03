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

    public static function new($type, $recipients, $entityId, $url)
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
                'url' => $url
            ];
            
            $newNotification = new Notification($data);
            $newNotification->save();
        }

    }
}
