<?php

namespace App\Http\Controllers\Daily;

use App\Http\Controllers\Controller;
use App\Models\Daily\Daily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class DailyController extends Controller
{
    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'task_id' => ['required'],
            'activity_name' => ['required'],
            'start_date' => ['required'],
            'end_date' => ['required'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => $validator->errors()
            ], 500));
        }

        $employeId = Auth::user()->employe_id;

        $newDaily = new Daily();
        $newDaily->task_id = $request->task_id;
        $newDaily->employe_id = $employeId;
        $newDaily->activity_name = $request->activity_name;
        $newDaily->start_date = $request->start_date;
        $newDaily->end_date = $request->end_date;

        if(!$newDaily->save()){
            throw new HttpResponseException(response()->json([
                "status" => false,
                "message" => "Failed to create daily."
            ], 500));
        }

        return response()->json([
            "status" => true,
            "message" => "From daily store controller"
        ], 200);

    }
}
