<?php

namespace App\Http\Controllers\Kontrak;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PostResource;

class ContractController extends Controller
{
    /**
     * List all kontrak
     */
    public function index(Request $request)
    {
        $data = DB::table('kontrak')->orderBy('id', 'DESC')->get();
        return new PostResource(true, 'list kontrak', $data);
    }

    /**
     * Show single kontrak
     */
    public function show($id)
    {
        $row = DB::table('kontrak')->where('id', $id)->first();
        if (is_null($row)) {
            return new PostResource(false, 'not found', []);
        }
        return new PostResource(true, 'detail kontrak', $row);
    }

    /**
     * Insert new kontrak
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'no_contrac' => 'required|string|max:100',
            'judul'      => 'required|string|max:255',
            'partner'    => 'required|string|max:255',
            'start'      => 'nullable|date',
            'end'        => 'required|date',
            'pic'        => 'required|string|max:30',
            'created_by' => 'nullable|string|max:30',
        ]);

        $insert = [
            'no_contrac' => $validated['no_contrac'],
            'judul'      => $validated['vjudul'],
            'partner'    => $validated['vpartner'],
            'start'      => isset($validated['start']) ? $validated['start'] : null,
            'end'        => $validated['end'],
            'pic'        => $validated['pic'],
            'created_by' => isset($validated['created_by']) ? $validated['created_by'] : null,
        ];

        $id = DB::table('kontrak')->insertGetId($insert);
        $new = DB::table('kontrak')->where('id', $id)->first();
        return new PostResource(true, 'created', $new);
    }

    /**
     * Update existing kontrak
     */
    public function update(Request $request, $id)
    {
        if (!DB::table('kontrak')->where('id', $id)->exists()) {
            return new PostResource(false, 'not found', []);
        }

        $validated = $request->validate([
            'no_contrac' => 'sometimes|required|string|max:100',
            'judul'      => 'sometimes|required|string|max:255',
            'partner'    => 'sometimes|required|string|max:255',
            'start'      => 'sometimes|nullable|date',
            'end'        => 'sometimes|required|date',
            'pic'        => 'sometimes|required|string|max:30',
            'created_by' => 'sometimes|nullable|string|max:30',
        ]);

        $payload = array_intersect_key($validated, array_flip([
            'no_contrac', 'judul', 'partner', 'start', 'end', 'pic', 'created_by'
        ]));

        DB::table('kontrak')->where('id', $id)->update($payload);
        $updated = DB::table('kontrak')->where('id', $id)->first();
        return new PostResource(true, 'updated', $updated);
    }

    /**
     * Delete kontrak
     */
    public function destroy($id)
    {
        $deleted = DB::table('kontrak')->where('id', $id)->delete();
        if ($deleted) {
            return new PostResource(true, 'deleted', []);
        }
        return new PostResource(false, 'not found', []);
    }
}
