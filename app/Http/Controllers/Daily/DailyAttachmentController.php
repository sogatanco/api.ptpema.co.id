<?php

namespace App\Http\Controllers\Daily;

use App\Http\Controllers\Controller;
use App\Models\Daily\DailyAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DailyAttachmentController extends Controller
{
    public function index(Request $request) {

        $validator = Validator::make($request->all(), [
            'daily_id' => 'required|exists:daily,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attachments = DailyAttachment::where('daily_id', $request->daily_id)->get();

        $data = $attachments->map(function ($item) {
            return [
                'id' => $item->id,
                'original_name' => $item->original_name,
                'file_url' => asset('storage/' . $item->file_path),
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'created_at' => $item->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function store(Request $request){

        // Cek apakah attachment adalah array atau satu file
        $isMultiple = is_array($request->file('attachment'));

        // Validasi fleksibel
        $rules = [
            'daily_id' => 'required|exists:daily,id',
            'attachment' => $isMultiple ? 'required|array' : 'required|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx,xlsx,xls',
        ];

        if ($isMultiple) {
            $rules['attachment.*'] = 'file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx,xlsx,xls';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Proses upload
        $uploadedFiles = $isMultiple ? $request->file('attachment') : [$request->file('attachment')];
        $attachments = [];

        foreach ($uploadedFiles as $file) {
            $path = $file->store('attachments', 'public');

            $attachment = new DailyAttachment();
            $attachment->daily_id = $request->daily_id;
            $attachment->file_path = $path;
            $attachment->original_name = $file->getClientOriginalName();
            $attachment->mime_type = $file->getClientMimeType();
            $attachment->size = $file->getSize();
            $attachment->save();

            $attachments[] = $attachment;
        }

        return response()->json([
            'message' => 'File berhasil diunggah',
            'data' => $isMultiple ? $attachments : $attachments[0],
        ], 201);
    }

    public function destroy(Request $request) {

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:daily_attachments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $deletedFiles = [];

        $attachments = DailyAttachment::whereIn('id', $request->ids)->get();

        foreach ($attachments as $attachment) {
            // Hapus file dari storage
            if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $deletedFiles[] = $attachment->id;

            // Hapus dari database
            $attachment->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'File berhasil dihapus',
            'deleted_ids' => $deletedFiles,
        ]);

    }
}
