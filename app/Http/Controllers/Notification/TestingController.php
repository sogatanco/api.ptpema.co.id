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

        $recipientArray = Comment::select('employe_id')
                    ->where('task_id', $task)->get();

        $recipient = '212323434L';

        $entityId = 1;
        $url = 'https://www.google.com';

        NotificationController::new('CREATE_PROJECT', $recipient, $entityId, $url);

        return response()->json([
            'message' => "sukses",
            'comment_data' => $recipient->toArray()
        ]);
    }
}
