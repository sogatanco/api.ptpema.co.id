<?php

namespace App\Http\Controllers\Comment;

use App\Http\Controllers\Controller;
use App\Models\Comment\Comment;
use App\Models\Employe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($taskId)
    {
        $task = Comment::select('comment_id', 'comments.comment', 'comments.created_at', 'comments.employe_id', 'employees.first_name')
                ->join('employees', 'employees.employe_id', '=', 'comments.employe_id')    
                ->where('task_id', $taskId)
                ->get();

        return response()->json([
            "status" => true,
            "data" => $task
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'employe_id' => ['required'],
            'task_id' => ['required'],
            'comment' => ['required']
        ]);

        if($validator->fails()){
            return response()->json([
                "message" => $validator->errors()
            ], 400);
        }

        $newComment = new Comment($request->all());
        $saved = $newComment->save();

        if($saved){
            $employe = Employe::select('first_name')
                    ->where('employe_id', $newComment->employe_id)
                    ->first();

            $newComment['first_name'] = $employe->first_name;

            return response()->json([
                "status" => true,
                "data" => $newComment
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "errors" => "Something went wrong."
            ], 500));
        }
        
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $commentId)
    {
        $validator = Validator::make($request->all(), [
            'comment' => ['required']
        ]);

        if($validator->fails()){
            return response()->json([
                "message" => $validator->errors()
            ], 400);
        }

        $where = ['comment_id' => $commentId, 'employe_id' => $request->employe_id];
        $isUpdated = Comment::where($where)
                    ->update(['comment' => $request->comment]);

        if($isUpdated){
            $updatedTask = Comment::where($where)
                            ->first();

            return response()->json([
                "status" => true,
                "data" => $updatedTask,
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "errors" => [
                    "message" => "Update comment failed."
                ]
            ], 400));
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, $employe_id)
    {
        $where = ['comment_id' => $id, 'employe_id' => $employe_id];
        $deletedComment = Comment::where($where)->delete();

        if($deletedComment){
            return response()->json([
                "status" => true,
                "message" => "Comment has been deleted."
            ],200);
        }else{
            throw new HttpResponseException(response([
                "errors" => [
                    "message" => "Delete comment failed."
                ]
            ], 400));
        }
    }
}