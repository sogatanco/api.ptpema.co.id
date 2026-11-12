<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\SuratDraft;
use App\Models\Employe;
use Illuminate\Http\Request;

/**
 * Draft surat: tersimpan tanpa nomor dan tanpa alur paraf. Nomor baru diambil saat draft di-submit lewat SuratController::insert.
 * Setiap draft hanya bisa dilihat dan diubah pembuatnya.
 */
class SuratDraftController extends Controller
{
    private function milikku($id)
    {
        return SuratDraft::where('id', $id)->where('created_by', Employe::employeId())->first();
    }

    private function tidakAda()
    {
        return response()->json(['success' => false, 'message' => 'Draft tidak ditemukan.'], 404);
    }

    // simpan draft baru, atau perbarui bila id diisi
    public function simpan(Request $request)
    {
        $payload = json_encode($request->only(['kepada', 'perihal', 'lampiran', 'jenislampiran', 'isiSurat', 'tembusans', 'divisi', 'ttdBy', 'bhs', 'ttdBasah', 'fileLampiran']), JSON_UNESCAPED_UNICODE);

        if ($request->filled('id')) {
            $draft = $this->milikku($request->id);
            if (!$draft) {
                return $this->tidakAda();
            }
            $draft->update(['payload' => $payload]);
        } else {
            $draft = SuratDraft::create(['created_by' => Employe::employeId(), 'payload' => $payload]);
        }

        return new PostResource(true, 'Draft tersimpan', ['id' => $draft->id]);
    }

    public function daftar()
    {
        $rows = SuratDraft::where('created_by', Employe::employeId())->latest('updated_at')->get();

        $data = $rows->map(function ($r) {
            $p = json_decode($r->payload, true) ?: [];

            return [
                'id' => $r->id,
                'kepada' => $p['kepada'] ?? '',
                'perihal' => $p['perihal'] ?? '',
                'ttd_basah' => filter_var($p['ttdBasah'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ];
        });

        return new PostResource(true, 'draft surat', $data);
    }

    public function detail($id)
    {
        $draft = $this->milikku($id);
        if (!$draft) {
            return $this->tidakAda();
        }
        $p = json_decode($draft->payload, true) ?: [];
        $p['id'] = $draft->id;

        return new PostResource(true, 'draft surat', $p);
    }

    public function hapus($id)
    {
        $draft = $this->milikku($id);
        if (!$draft) {
            return $this->tidakAda();
        }
        $draft->delete();

        return new PostResource(true, 'Draft dihapus', []);
    }
}
