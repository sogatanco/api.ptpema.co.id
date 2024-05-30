<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controller\Notification\NotificationController;


class TestingController extends Controller
{
    public function newNotification()
    {
        $userId = Auth::user()->id;

        $result  = (new NotificationController)->createSubActivity($userId);

        return response()->json([
            'data' => $result
        ]);
    }
}
