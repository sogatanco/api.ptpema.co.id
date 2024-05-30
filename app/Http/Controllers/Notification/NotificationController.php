<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;

class NotificationController extends Controller
{
    public function createSubActivity($employeId)
    {
        // save notification
        $employe = employee::where('employe_id', $employeId)->first();

        $directSupervisorId = Structure::where('employe_id', $employeId)->first()->direct_atasan;

        $data = [
            'actor' => $employeId,
            'recipient' => $directSupervisorId,
            'title' => $employe->first_name . 'Membuat Task Baru',
            'category' => 'Task'
        ];

        $newNotification = new Notification($data);
        $newNotification->save();

        return $newNotification;

    }
}
