<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\Notif\Notifikasi as NotifikasiBaru;

class NotificationController extends Controller
{

    /**
     * Semua notifikasi (ADM, SPPD, mobil, asset, project, task, pengajuan, vendor, tender) disimpan di ptpemaco_notif lewat Hub.
     * Tabel ptpemaco_apam.notifications tidak dipakai lagi. Untuk modul vendor pelakunya perusahaan, bukan karyawan.
     */
    public static function new($type, $recipients, $entityId)
    {
        $actor = str_starts_with((string) $type, 'VENDOR_') ? null : Employe::employeId();
        \App\Services\Notifikasi\TipeLama::kirim((string) $type, $recipients, $entityId, $actor);
    }

    /** bel: hanya notifikasi dari ptpemaco_notif; tabel notifikasi lama (ptpemaco_apam.notifications) tidak dibaca lagi */
    public function get()
    {
        return response()->json([
            'status' => true,
            'data' => $this->notifikasiBaru(Employe::employeId()),
        ], 200);
    }

    /** bentuknya disamakan dengan notifikasi lama (id diberi awalan "n") supaya bel di frontend tidak berubah */
    private function notifikasiBaru($employeId): array
    {
        if (!config('notifikasi.aktif')) {
            return [];
        }
        try {
            return NotifikasiBaru::where('recipient', $employeId)->whereNull('read_at')->orderByDesc('id')->limit(100)->get()
                ->map(fn ($n) => [
                    'id' => 'n' . $n->id,
                    'entity_id' => null,
                    'created_at' => $n->created_at,
                    'type' => strtoupper($n->event),
                    'message' => $n->isi,
                    'url' => $n->url,
                    'link' => $n->link ?: \App\Services\Notifikasi\Hub::link($n->url),
                    'query_key' => null,
                    'entity' => strtoupper($n->modul),
                    'actor' => $n->actor_name ?: '-',
                    'position' => $n->judul,
                ])->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** halaman "semua notifikasi": yang sudah dan belum dibaca, ?status=belum|sudah, ?modul=, ?page= */
    public function semua(\Illuminate\Http\Request $request)
    {
        $q = NotifikasiBaru::where('recipient', Employe::employeId());
        $belum = (clone $q)->whereNull('read_at')->count();
        if ($request->status === 'belum') {
            $q->whereNull('read_at');
        } elseif ($request->status === 'sudah') {
            $q->whereNotNull('read_at');
        }
        if ($request->filled('modul')) {
            $q->where('modul', $request->modul);
        }
        $hal = $q->orderByDesc('id')->paginate(20);

        return response()->json([
            'status' => true,
            'belum_dibaca' => $belum,
            'total' => $hal->total(),
            'halaman' => $hal->currentPage(),
            'halaman_terakhir' => $hal->lastPage(),
            'data' => collect($hal->items())->map(fn ($n) => [
                'id' => $n->id,
                'modul' => $n->modul,
                'event' => $n->event,
                'judul' => $n->judul,
                'isi' => $n->isi,
                'url' => $n->url,
                'actor' => $n->actor_name ?: '-',
                'created_at' => $n->created_at,
                'read_at' => $n->read_at,
            ])->all(),
        ], 200);
    }

    /** tandai satu notifikasi dibaca (id angka) atau semuanya (tanpa id) */
    public function baca(\Illuminate\Http\Request $request)
    {
        $q = NotifikasiBaru::where('recipient', Employe::employeId())->whereNull('read_at');
        if ($request->filled('id')) {
            $q->where('id', (int) $request->id);
        }
        $q->update(['read_at' => now()]);

        return response()->json(['status' => true], 200);
    }

    public function delete($id)
    {
        if (is_string($id) && str_starts_with($id, 'n')) {
            NotifikasiBaru::where('id', (int) substr($id, 1))->where('recipient', Employe::employeId())->update(['read_at' => now()]);
        }

        return response()->json(['status' => true, 'message' => 'Notification has been deleted'], 200);
    }
}
