<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\Structure;

class NotificationController extends Controller
{
    public function createSubActivity()
    {
        // save notification
        $userId = Auth::user()->id;
        $employeId = Employe::where('user_id', $userId)->first();

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
