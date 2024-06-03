<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Notification\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct($type, $recipients, $entityId, $url){
        $this->type  = $type;
        $this->recipients = $recipients;
        $this->entityId = $entityId;
        $this->url = $url;
    }

    public function create()
    {
        $userId = Auth::user()->id;
        $employe = Employe::where('user_id', $userId)->first();

        // choose entity
        $entityTypeId = NotificationEntityType::where('entity', $this->type)->first()->id;

        $data = [
            'actor' => $employe->employe_id,
            'recipient' => $recipients,
            'entity_type_id' => $entityTypeId,
            'url' => $url
        ];

        $newNotification = new Notification($data);
        $newNotification->save();

    }
}
