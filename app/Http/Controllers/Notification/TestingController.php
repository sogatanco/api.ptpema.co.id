<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Notification\NotificationController;
use App\Models\Comment\Comment;

class TestingController extends Controller
{
    public function newNotification()
    {

        $task = 99;

        $recipients = Comment::select('employe_id')
                    ->where('task_id', $task)->get();
                    
        $entityId = 1;

        NotificationController::new('UPDATE_TASK', $recipients, $entityId);

        return response()->json([
            'message' => "sukses",
        ]);
    }
}
