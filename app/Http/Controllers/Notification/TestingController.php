<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Notification\NotificationController;
use Illuminate\Support\Facades\Auth;

class TestingController extends Controller
{
    public function newNotification()
    {
        $result  = (new NotificationController)->createSubActivity();

        return response()->json([
            'data' => $result
        ]);
    }
}
