<?php

namespace App\Http\Controllers\Kontrak;

use App\Http\Controllers\Controller;
use App\Models\Kontrak\Contract;
use App\Models\Kontrak\ContractHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'created_by' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $request->only(['no_contrac', 'judul', 'partner', 'start', 'end', 'pic', 'created_by']);
        $payload['created_by'] = $payload['created_by'] ?? auth()->id() ?? (auth()->user()?->employe_id ?? null);

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
