<?php

namespace App\Services\Notifikasi;

use App\Models\Employe;
use App\Models\Notif\Notifikasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pusat notifikasi: menyimpan ke ptpemaco_notif, lalu mengirim ke web push, FCM dan WhatsApp setelah respons selesai
 * sehingga kegagalan kanal tidak pernah menggagalkan proses bisnis (approval, submit, dll).
 */
class Hub
{
    public static function aktif(): bool
    {
        return (bool) config('notifikasi.aktif');
    }

    /**
     * @param string|array|null $penerima employe_id atau daftar employe_id
     * @param array $p modul, event, judul, isi, url?, ref?, data?
     */
    public static function kirim($penerima, ?string $actor, array $p): void
    {
        if (!self::aktif()) {
            return;
        }

        try {
            // pelaku tidak perlu diberi tahu tentang tindakannya sendiri, kecuali pesan meminta 'termasuk_pelaku'
            // (mis. backdate: satu orang bisa menjadi AdminAdm sekaligus PICAdm dan tetap perlu menerima permintaannya)
            $termasukPelaku = !empty($p['termasuk_pelaku']);
            $daftar = array_values(array_unique(array_filter((array) $penerima, fn ($x) => is_string($x) && $x !== '' && $x !== '-' && ($termasukPelaku || $x !== $actor))));
            if (!$daftar) {
                return;
            }
            $namaActor = $actor ? Employe::where('employe_id', $actor)->value('first_name') : null;
            $ids = [];
            foreach ($daftar as $r) {
                $n = Notifikasi::create([
                    'modul' => $p['modul'],
                    'event' => $p['event'],
                    'recipient' => $r,
                    'actor' => $actor,
                    'actor_name' => $namaActor,
                    'judul' => mb_substr($p['judul'], 0, 150),
                    'isi' => $p['isi'],
                    'url' => $p['url'] ?? null,
                    'link' => self::link($p['url'] ?? null),
                    'ref' => isset($p['ref']) ? mb_substr((string) $p['ref'], 0, 120) : null,
                    'data' => $p['data'] ?? null,
                    'created_at' => now(),
                ]);
                $ids[] = $n->id;
            }
            // dikirim setelah respons ke pengguna terkirim
            dispatch(function () use ($ids) {
                foreach ($ids as $id) {
                    Pengirim::kirimSemua($id);
                }
            })->afterResponse();
        } catch (\Throwable $e) {
            // notifikasi tidak boleh membatalkan proses utama
            Log::warning('Notifikasi gagal disimpan', ['event' => $p['event'] ?? null, 'error' => $e->getMessage()]);
        }
    }

    /** tautan lengkap ke halaman di sys; disimpan di tabel notifikasi supaya pengirim WhatsApp dan push memakai nilai yang sama */
    public static function link(?string $url): string
    {
        return rtrim((string) config('notifikasi.url_web'), '/') . '/' . ltrim((string) $url, '/');
    }

    public static function tandaiDibaca(int $id, string $employeId): bool
    {
        return Notifikasi::where('id', $id)->where('recipient', $employeId)->whereNull('read_at')->update(['read_at' => now()]) > 0;
    }

    public static function catatPengiriman(int $notifId, string $channel, string $status, ?string $target = null, ?string $error = null): void
    {
        DB::connection('notif')->table('notification_deliveries')->insert([
            'notification_id' => $notifId,
            'channel' => $channel,
            'status' => $status,
            'target' => $target ? mb_substr($target, 0, 255) : null,
            'attempts' => 1,
            'error' => $error ? mb_substr($error, 0, 500) : null,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
        ]);
    }
}
