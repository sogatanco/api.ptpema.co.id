<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\NotificationController;

class TestingController extends Controller
{
    public function newNotification()
    {

        $recipient = '20230977K';
        $entityId = 1;
        $url = 'https://www.google.com';

        new NotificationController('CREATE_TASK', $resipient, $entityId, $url);

        return response()->json([
            'data' => "testing notification"
        ]);
    }
}
