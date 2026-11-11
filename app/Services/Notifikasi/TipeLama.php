<?php

namespace App\Services\Notifikasi;

use App\Models\Employe;
use App\Models\Projects\Project;
use App\Models\Tasks\Task;
use App\Models\Vendor\Perusahaan;
use App\Models\Vendor\Tender;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi project, task, komentar, pengajuan, vendor dan tender dulu ditulis ke ptpemaco_apam.notifications lewat
 * NotificationController::new(). Pemetaan ini meneruskan pemanggilan yang sama ke Hub (ptpemaco_notif) dengan isi yang rinci,
 * sehingga pemanggilnya tidak perlu diubah dan tabel lama tidak dipakai lagi.
 */
class TipeLama
{
    public static function kirim(string $tipe, $penerima, $entityId, ?string $actor): void
    {
        if (!Hub::aktif()) {
            return;
        }
        try {
            $daftar = self::daftarPenerima($penerima);
            if (!$daftar) {
                return;
            }
            $p = self::pesan($tipe, $entityId, $actor);
            if (!$p) {
                Log::warning('Tipe notifikasi tidak dikenal', ['tipe' => $tipe]);

                return;
            }
            Hub::kirim($daftar, $actor, $p + ['ref' => $entityId === null || $entityId === '' ? null : (string) $entityId]);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi tipe lama gagal', ['tipe' => $tipe, 'error' => $e->getMessage()]);
        }
    }

    /** penerima bisa berupa employe_id, daftar employe_id, atau daftar/koleksi baris ['employe_id' => ...] */
    private static function daftarPenerima($penerima): array
    {
        if (is_string($penerima)) {
            return [$penerima];
        }
        $hasil = [];
        foreach ((is_object($penerima) && method_exists($penerima, 'toArray') ? $penerima->toArray() : (array) $penerima) as $r) {
            $hasil[] = is_array($r) || is_object($r) ? (string) (((array) $r)['employe_id'] ?? '') : (string) $r;
        }

        return array_values(array_filter($hasil));
    }

    private static function nama(?string $employeId): string
    {
        return ($employeId ? Employe::where('employe_id', $employeId)->value('first_name') : null) ?: 'Seseorang';
    }

    /** entity_id task berbentuk "<project_id>/<task_id>"; komentar hanya mengirim task_id */
    private static function task($entityId): array
    {
        [$projectId, $taskId] = array_pad(explode('/', (string) $entityId, 2), 2, null);
        if ($taskId === null) {
            $taskId = $projectId;
            $projectId = Task::where('task_id', $taskId)->value('project_id');
        }
        $judul = Task::where('task_id', $taskId)->value('task_title') ?: ('#' . $taskId);
        $proyek = Project::where('project_id', $projectId)->value('project_name') ?: ('#' . $projectId);

        return [$projectId, $taskId, $judul, $proyek];
    }

    private static function pesan(string $tipe, $entityId, ?string $actor): ?array
    {
        $pelaku = self::nama($actor);

        $tugas = [
            'TAG_TASK' => ['task', 'task.ditandai', 'Anda ditandai pada task', '%s menandai Anda pada task "%s" di project "%s".'],
            'TAG_SUB_ACTIVITY' => ['task', 'task.sub_aktivitas', 'Anda ditandai pada aktivitas', '%s menandai Anda pada aktivitas "%s" di project "%s".'],
            'CREATE_COMMENT' => ['task', 'task.komentar', 'Komentar baru pada task', '%s menambahkan komentar pada task "%s" di project "%s".'],
            'UPLOAD_TASK_FILE' => ['task', 'task.file', 'File baru pada task', '%s mengunggah file pada task "%s" di project "%s".'],
            'REVIEW_TASK' => ['project', 'project.review_task', 'Task menunggu persetujuan Anda', '%s meminta persetujuan task "%s" di project "%s". Mohon ditinjau.'],
            'APPROVED_TASK' => ['project', 'project.task_disetujui', 'Task Anda disetujui', '%s menyetujui task "%s" di project "%s".'],
            'REVISED_TASK' => ['project', 'project.task_direvisi', 'Task Anda perlu direvisi', '%s meminta revisi task "%s" di project "%s".'],
        ];
        if (isset($tugas[$tipe])) {
            [$projectId, $taskId, $judul, $proyek] = self::task($entityId);
            [$modul, $event, $tajuk, $isi] = $tugas[$tipe];

            return [
                'modul' => $modul, 'event' => $event, 'judul' => $tajuk,
                'isi' => sprintf($isi, $pelaku, $judul, $proyek),
                'url' => 'projects/details/' . $projectId . '?task=' . $taskId,
            ];
        }

        switch ($tipe) {
            case 'APPROVED_PENGAJUAN':
                return ['modul' => 'pengajuan', 'event' => 'pengajuan.review', 'judul' => 'Pengajuan menunggu review Anda',
                    'isi' => "{$pelaku} mengajukan pengajuan. Mohon ditinjau.", 'url' => 'pengajuan'];

            case 'TENDER_CREATED':
            case 'TENDER_STATUS_CHANGED':
            case 'TENDER_BA_SUBMITTED':
            case 'TENDER_BA_REVISED':
            case 'TENDER_BA_APPROVED':
                $nama = $entityId ? Tender::where('id_tender', $entityId)->value('nama_tender') : null;
                $objek = $nama ? "pengadaan \"{$nama}\"" : 'pengadaan';
                $teks = [
                    'TENDER_CREATED' => ['Pengadaan baru dibuat', "{$pelaku} membuat {$objek}."],
                    'TENDER_STATUS_CHANGED' => ['Status pengadaan berubah', "{$pelaku} mengubah status {$objek}."],
                    'TENDER_BA_SUBMITTED' => ['Berita acara menunggu review Anda', "{$pelaku} meminta review berita acara {$objek}. Mohon ditinjau."],
                    'TENDER_BA_REVISED' => ['Berita acara perlu direvisi', "{$pelaku} meminta revisi berita acara {$objek}."],
                    'TENDER_BA_APPROVED' => ['Berita acara disetujui', "{$pelaku} menyetujui berita acara {$objek}."],
                ][$tipe];
                $review = $tipe === 'TENDER_BA_SUBMITTED';

                return ['modul' => 'tender', 'event' => 'tender.' . strtolower(substr($tipe, 7)), 'judul' => $teks[0], 'isi' => $teks[1],
                    'url' => $review ? 'tender/approval' : 'vendor/tender'];

            case 'VENDOR_REGISTERED':
            case 'VENDOR_REVIEW_SUBMIT':
            case 'VENDOR_REVIEW_UPDATE':
                // aktornya perusahaan, bukan karyawan
                $p = $entityId ? Perusahaan::select('bentuk_usaha', 'nama_perusahaan')->where('id', $entityId)->first() : null;
                $nama = $p ? trim($p->bentuk_usaha . ' ' . $p->nama_perusahaan) : 'Perusahaan';
                $teks = [
                    'VENDOR_REGISTERED' => ['Perusahaan baru mendaftar', "{$nama} mendaftar di IVDS."],
                    'VENDOR_REVIEW_SUBMIT' => ['Data perusahaan menunggu review', "{$nama} meminta review data. Mohon ditinjau."],
                    'VENDOR_REVIEW_UPDATE' => ['Update data perusahaan menunggu review', "{$nama} meminta review update data. Mohon ditinjau."],
                ][$tipe];

                return ['modul' => 'vendor', 'event' => 'vendor.' . strtolower(substr($tipe, 7)), 'judul' => $teks[0], 'isi' => $teks[1],
                    'url' => 'vendor/requests/check' . ($entityId ? '/' . $entityId : '')];
        }

        return null;
    }

    /** komentar daily: pemilik daily dan penulis komentar yang dibalas diberi tahu */
    public static function daily(\App\Models\Daily\DailyComment $komentar): void
    {
        try {
            $daily = \App\Models\Daily\Daily::find($komentar->daily_id);
            if (!$daily) {
                return;
            }
            $dibalas = $komentar->reply_id ? \App\Models\Daily\DailyComment::where('id', $komentar->reply_id)->value('employe_id') : null;
            $isi = self::nama($komentar->employe_id) . " mengomentari daily \"{$daily->activity_name}\": " . mb_substr((string) $komentar->comment, 0, 120);
            Hub::kirim(array_filter([$daily->employe_id, $dibalas]), $komentar->employe_id, [
                'modul' => 'daily', 'event' => 'daily.komentar', 'judul' => 'Komentar baru pada daily',
                'isi' => $isi, 'url' => 'daily?daily=' . $daily->id, 'ref' => (string) $daily->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi daily gagal', ['error' => $e->getMessage()]);
        }
    }

    /** BAST dan handover project (dulu ditulis langsung ke project_notifications) */
    public static function proyek(?string $actor, ?string $penerima, $projectId, string $event, string $judul, string $aksi): void
    {
        try {
            $proyek = Project::where('project_id', $projectId)->value('project_name') ?: ('#' . $projectId);
            Hub::kirim($penerima, $actor, [
                'modul' => 'project', 'event' => 'project.' . $event, 'judul' => $judul,
                'isi' => self::nama($actor) . " {$aksi} untuk project \"{$proyek}\".",
                'url' => 'projects/details/' . $projectId, 'ref' => (string) $projectId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi project gagal', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
