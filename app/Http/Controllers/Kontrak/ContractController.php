<?php

namespace App\Http\Controllers\Kontrak;

use App\Http\Controllers\Controller;
use App\Models\Kontrak\Contract;
use App\Models\Kontrak\ContractHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::withTrashed()->orderByDesc('id')->get();

        return response()->json([
            'status' => true,
            'message' => 'List kontrak berhasil dimuat',
            'data' => $contracts,
        ], 200);
    }

    public function show($id)
    {
        $contract = Contract::withTrashed()->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail kontrak berhasil dimuat',
            'data' => $contract,
        ], 200);
    }

    public function store(Request $request)
    {
        $noContrac = trim((string) $request->input('no_contrac', ''));

        if ($noContrac !== '' && Contract::on('w12')->where('no_contrac', $noContrac)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Nomor kontrak sudah ada',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'no_contrac' => ['required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:255'],
            'partner' => ['nullable', 'string', 'max:255'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'pic' => ['nullable', 'string', 'max:255'],
            'created_by' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $request->only(['no_contrac', 'judul', 'partner', 'start', 'end', 'pic', 'created_by']);
        $payload['created_by'] = $payload['created_by'] ?? auth()->user()?->employe_id ?? auth()->id() ?? null;

        $fileInput = $request->file('file');
        $base64Input = $request->input('file');

        if ($request->hasFile('file')) {
            $file = $fileInput;
            $directory = public_path('kontak');

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                return response()->json([
                    'status' => false,
                    'message' => 'File kontrak gagal diupload',
                    'errors' => ['file' => ['The file failed to upload.']],
                ], 422);
            }

            $originalName = $file->getClientOriginalName();
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $payload['file_url'] = 'kontak/' . $filename;
        } elseif (is_string($base64Input) && trim($base64Input) !== '') {
            $pdfData = preg_replace('/^data:application\/pdf;base64,/', '', trim($base64Input));
            $decoded = base64_decode($pdfData, true);

            if ($decoded === false) {
                return response()->json([
                    'status' => false,
                    'message' => 'File kontrak tidak valid',
                    'errors' => ['file' => ['The file failed to upload.']],
                ], 422);
            }

            $directory = public_path('kontak');
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $filename = 'contract_' . time() . '_' . Str::random(8) . '.pdf';
            file_put_contents($directory . '/' . $filename, $decoded);

            $payload['file_url'] = 'kontak/' . $filename;
        }

        if (isset($payload['file_url']) && !empty($payload['file_url'])) {
            $payload['file_url'] = str_replace('\\', '/', $payload['file_url']);
        }

        $contract = Contract::create($payload);
        $this->logHistory($contract->id, 'created');

        return response()->json([
            'status' => true,
            'message' => 'Kontrak berhasil ditambahkan',
            'data' => $contract,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $contract = Contract::withTrashed()->findOrFail($id);

        if ($request->has('no_contrac')) {
            $newNoContrac = trim((string) $request->input('no_contrac'));
            $exists = Contract::on('w12')
                ->where('no_contrac', $newNoContrac)
                ->where('id', '!=', $contract->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Nomor kontrak sudah ada',
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'no_contrac' => ['sometimes', 'string', 'max:100'],
            'judul' => ['sometimes', 'string', 'max:255'],
            'partner' => ['nullable', 'string', 'max:255'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'pic' => ['nullable', 'string', 'max:255'],
            'created_by' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $request->only(['no_contrac', 'judul', 'partner', 'start', 'end', 'pic', 'created_by']);

        $fileInput = $request->file('file');
        $base64Input = $request->input('file');

        if ($request->hasFile('file')) {
            $file = $fileInput;
            $directory = public_path('kontak');

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                return response()->json([
                    'status' => false,
                    'message' => 'File kontrak gagal diupload',
                    'errors' => ['file' => ['The file failed to upload.']],
                ], 422);
            }

            if (!empty($contract->file_url) && file_exists(public_path($contract->file_url))) {
                unlink(public_path($contract->file_url));
            }

            $originalName = $file->getClientOriginalName();
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $payload['file_url'] = 'kontak/' . $filename;
        } elseif (is_string($base64Input) && trim($base64Input) !== '') {
            $pdfData = preg_replace('/^data:application\/pdf;base64,/', '', trim($base64Input));
            $decoded = base64_decode($pdfData, true);

            if ($decoded === false) {
                return response()->json([
                    'status' => false,
                    'message' => 'File kontrak tidak valid',
                    'errors' => ['file' => ['The file failed to upload.']],
                ], 422);
            }

            if (!empty($contract->file_url) && file_exists(public_path($contract->file_url))) {
                unlink(public_path($contract->file_url));
            }

            $directory = public_path('kontak');
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $filename = 'contract_' . time() . '_' . Str::random(8) . '.pdf';
            file_put_contents($directory . '/' . $filename, $decoded);

            $payload['file_url'] = 'kontak/' . $filename;
        }

        if (isset($payload['file_url']) && !empty($payload['file_url'])) {
            $payload['file_url'] = str_replace('\\', '/', $payload['file_url']);
        }

        $employeeId = $payload['created_by'] ?? auth()->user()?->employe_id ?? auth()->id();
        $payload['created_by'] = $employeeId;

        $contract->fill($payload);
        $contract->save();

        $this->logHistory($contract->id, 'updated');

        return response()->json([
            'status' => true,
            'message' => 'Kontrak berhasil diperbarui',
            'data' => $contract->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $contract = Contract::withTrashed()->findOrFail($id);
        $contract->delete();

        $this->logHistory($contract->id, 'deleted');

        return response()->json([
            'status' => true,
            'message' => 'Kontrak berhasil dihapus',
        ], 200);
    }

    public function history($id)
    {
        $contract = Contract::withTrashed()->findOrFail($id);

        $histories = ContractHistory::where('contract_id', $contract->id)
            ->orderByDesc('action_time')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Riwayat kontrak berhasil dimuat',
            'data' => $histories,
        ], 200);
    }

    protected function logHistory($contractId, $action)
    {
        ContractHistory::create([
            'contract_id' => $contractId,
            'action' => $action,
            'action_by' => auth()->id() ?? (auth()->user()?->employe_id ?? 'system'),
            'action_time' => now(),
        ]);
    }
}
