<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Notification\NotificationController;

class TestingController extends Controller
{
    public function newNotification()
    {

        $recipient = '20230977K';
        $entityId = 1;
        $url = 'https://www.google.com';

        NotificationController::New('CREATE_PROJECT', $recipient, $entityId, $url);

        return response()->json([
            'message' => "sukses",
        ]);
    }
}
