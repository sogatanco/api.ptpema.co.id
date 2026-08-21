<?php

namespace App\Http\Controllers\Kontrak;

use App\Http\Controllers\Controller;
use App\Models\Kontrak\Contract;
use App\Models\Kontrak\ContractHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    private const CONTRACT_MAX_FILE_SIZE = 100 * 1024 * 1024;

    private function contractStorageDisk()
    {
        if (config('filesystems.disks.public_benda')) {
            return Storage::disk('public_benda');
        }

        return Storage::disk('public');
    }

    private function validateContractFileSize(int $bytes, string $source = 'file')
    {
        if ($bytes > self::CONTRACT_MAX_FILE_SIZE) {
            throw new \RuntimeException('Ukuran file kontrak maksimal 100 MB');
        }

        return true;
    }

    private function normalizeContractFileUrl(string $fileUrl): string
    {
        $normalized = str_replace('\\', '/', trim($fileUrl));
        $normalized = preg_replace('#^/+#', '', $normalized);
        $normalized = preg_replace('#^contracts/contracts/#i', 'contracts/', $normalized);

        return $normalized;
    }

    private function formatContractResponse($contract)
    {
        $data = $contract instanceof \Illuminate\Database\Eloquent\Model ? $contract->toArray() : $contract;

        if (!empty($data['file_url'])) {
            $fileUrl = $this->normalizeContractFileUrl((string) $data['file_url']);
            $data['file_url'] = preg_match('#^https?://#i', $fileUrl) ? $fileUrl : url($fileUrl);
            $data['file_link'] = $data['file_url'];
        }

        return $data;
    }

    private function resolveContractFileData(Request $request, $existingContract = null)
    {
        $candidates = ['file', 'document', 'dokumen', 'pdf', 'lampiran'];

        foreach ($candidates as $name) {
            if ($request->hasFile($name)) {
                $file = $request->file($name);
                $directory = public_path('contracts');

                if (!is_dir($directory)) {
                    mkdir($directory, 0775, true);
                }

                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('File kontrak gagal diupload');
                }

                $this->validateContractFileSize((int) $file->getSize());

                if ($existingContract && !empty($existingContract->file_url) && file_exists(public_path($existingContract->file_url))) {
                    unlink(public_path($existingContract->file_url));
                }

                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
                $filename = time() . '_' . Str::slug($originalName) . '.' . $extension;

                $storedPath = $filename;
                $disk = $this->contractStorageDisk();
                $saved = $disk->put($storedPath, file_get_contents($file->getRealPath()));

                if (!$saved) {
                    throw new \RuntimeException('File kontrak gagal disimpan');
                }

                return ['file_url' => 'contracts/' . $storedPath];
            }
        }

        foreach ($candidates as $name) {
            $inputValue = $request->input($name);

            if (is_string($inputValue) && trim($inputValue) !== '') {
                if (str_starts_with(trim($inputValue), 'data:')) {
                    $pdfData = preg_replace('/^data:application\/pdf;base64,/', '', trim($inputValue));
                    $decoded = base64_decode($pdfData, true);

                    if ($decoded === false) {
                        throw new \RuntimeException('File kontrak tidak valid');
                    }

                    $this->validateContractFileSize(strlen($decoded));

                    $directory = public_path('contracts');
                    if (!is_dir($directory)) {
                        mkdir($directory, 0775, true);
                    }

                    if ($existingContract && !empty($existingContract->file_url) && file_exists(public_path($existingContract->file_url))) {
                        unlink(public_path($existingContract->file_url));
                    }

                    $filename = 'contract_' . time() . '_' . Str::random(8) . '.pdf';
                    $storedPath = $filename;
                    $disk = $this->contractStorageDisk();
                    $saved = $disk->put($storedPath, $decoded);

                    if (!$saved) {
                        throw new \RuntimeException('File kontrak gagal disimpan');
                    }

                    return ['file_url' => 'contracts/' . $storedPath];
                }

                if (preg_match('#^contracts/#i', trim($inputValue))) {
                    return ['file_url' => $this->normalizeContractFileUrl(trim($inputValue))];
                }
            }
        }

        return [];
    }

    public function index()
    {
        $contracts = Contract::query()->whereNull('deleted_at')->orderByDesc('id')->get();

        return response()->json([
            'status' => true,
            'message' => 'List kontrak berhasil dimuat',
            'data' => $contracts->map(fn ($contract) => $this->formatContractResponse($contract)),
        ], 200);
    }

    public function show($id)
    {
        $contract = Contract::withTrashed()->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail kontrak berhasil dimuat',
            'data' => $this->formatContractResponse($contract),
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

        try {
            $fileData = $this->resolveContractFileData($request);
            if (!empty($fileData)) {
                $payload = array_merge($payload, $fileData);
            }
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => ['file' => [$e->getMessage()]],
            ], 422);
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

        try {
            $fileData = $this->resolveContractFileData($request, $contract);
            if (!empty($fileData)) {
                $payload = array_merge($payload, $fileData);
            }
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => ['file' => [$e->getMessage()]],
            ], 422);
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
