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

        $comentData = Comment::where('task_id', $task)->get()->employe_id;

        $recipient = ['20230977K', '20230977L'];
        $entityId = 1;
        $url = 'https://www.google.com';

        NotificationController::new('CREATE_PROJECT', $recipient, $entityId, $url);

        return response()->json([
            'message' => "sukses",
            'comment_data' => $comentData
        ]);
    }
}
