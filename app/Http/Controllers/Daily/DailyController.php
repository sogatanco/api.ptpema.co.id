<?php

namespace App\Http\Controllers\Daily;

use App\Http\Controllers\Controller;
use App\Models\Daily\Daily;
use App\Models\Employe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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

        $employeId = Employe::employeId();

        $newDaily = new Daily();
        $newDaily->task_id = $request->task_id;
        $newDaily->employe_id = $employeId;
        $newDaily->activity_name = $request->activity_name;
        $newDaily->progress = 0;
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
            "message" => "Successfully created daily."
        ], 200);

    }

    public function update(Request $request){
        $validator = Validator::make($request->all(), [
            'daily_id' => ['required'],
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

        $updated = Daily::where('daily_id', $request->daily_id)->update([
            'task_id' => $request->task_id,
            'activity_name' => $request->activity_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        if($updated){
            return response()->json([
                "status" => true,
                "message" => "Successfully updated daily."
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Failed to update daily."
            ], 500);
        }
    }
}
