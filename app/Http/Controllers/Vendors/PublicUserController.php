<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Mail\PublicMessage;
use Mail;

class PublicUserController extends Controller
{
    public function sendMessage(Request $request)
    {
        $mailData = [
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'content' => $request->content
        ];

        if (Mail::to('pemasaran@pema.co.id')->send(new PublicMessage($mailData))) {
            return response()->json([
                'status' => true,
                'message' => 'Email sended successfully'
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Email failed to send"
            ], 500));
        }
    }
}
