<?php

namespace App\Http\Controllers\Daily;

use App\Http\Controllers\Controller;
use App\Models\Daily\DailyComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DailyCommentController extends Controller
{

    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'daily_id' => 'required|exists:daily,id',
            'employe_id' => 'required|exists:employees,employe_id',
            'comment' => 'required|string|max:1000',
            'reply_id' => 'nullable|exists:daily_comments,id',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx,xlsx,xls',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('comment_attachments', 'public');
        }

        $comment = DailyComment::create([
            'daily_id' => $request->daily_id,
            'employe_id' => $request->employe_id,
            'comment' => $request->comment,
            'reply_id' => $request->reply_id,
            'attachment_file' => $attachmentPath,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Komentar berhasil disimpan',
            'data' => $comment,
        ], 201);

    }

    public function list($dailyId){

        $comments = DailyComment::with([
            'employee:employe_id,first_name,last_name',
            'reply:id,comment,employe_id',
            'reply.employee:employe_id,first_name,last_name',
        ])
        ->where('daily_id', $dailyId)
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar komentar berhasil diambil',
            'data' => $comments,
        ], 200);

    }

    public function destroy($id)
    {

        $comment = DailyComment::with('replies')->find($id);

        if (!$comment) {
            return response()->json([
                'message' => 'Komentar tidak ditemukan',
            ], 404);
        }

        // Hapus attachment utama jika ada
        if ($comment->attachment_file && Storage::disk('public')->exists($comment->attachment_file)) {
            Storage::disk('public')->delete($comment->attachment_file);
        }

        // Hapus semua reply jika ada
        foreach ($comment->replies as $reply) {
            if ($reply->attachment_file && Storage::disk('public')->exists($reply->attachment_file)) {
                Storage::disk('public')->delete($reply->attachment_file);
            }

            $reply->delete();
        }

        // Hapus komentar utama
        $comment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Komentar dan balasan (jika ada) berhasil dihapus',
        ], 200);

    }
}
