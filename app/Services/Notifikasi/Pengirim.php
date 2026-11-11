<?php

namespace App\Services\Notifikasi;

use App\Models\Notif\DeviceToken;
use App\Models\Notif\Notifikasi;
use App\Models\Notif\PushSubscription;
use App\Models\Structure;
use App\Services\WhatsAppService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class Pengirim
{
    public static function kirimSemua(int $notifId): void
    {
        $n = Notifikasi::find($notifId);
        if (!$n) {
            return;
        }
        foreach (['webpush', 'fcm', 'wa'] as $kanal) {
            try {
                self::$kanal($n);
            } catch (\Throwable $e) {
                Hub::catatPengiriman($n->id, $kanal, 'failed', null, $e->getMessage());
                Log::warning('Kanal notifikasi gagal', ['kanal' => $kanal, 'notif' => $n->id, 'error' => $e->getMessage()]);
            }
        }
    }

    // WhatsApp lewat gateway yang sama dengan notifikasi konsumsi rapat
    private static function wa(Notifikasi $n): void
    {
        if (!Pengaturan::waAktif()) {
            Hub::catatPengiriman($n->id, 'wa', 'skipped', null, 'WhatsApp dinonaktifkan (halaman Setting Super Admin atau NOTIF_WA_AKTIF)');

            return;
        }
        if (!in_array($n->event, config('notifikasi.wa.event'), true)) {
            Hub::catatPengiriman($n->id, 'wa', 'skipped', null, "Event {$n->event} tidak ada di daftar WA (config/notifikasi.php)");

            return;
        }
        $hp = (string) Structure::where('employe_id', $n->recipient)->value('phone_number');
        $nomor = array_values(array_filter(preg_split('/[,;\/\s]+/', $hp), fn ($x) => preg_replace('/\D+/', '', $x) !== ''));
        if (!$nomor) {
            Hub::catatPengiriman($n->id, 'wa', 'skipped', null, 'Nomor HP penerima kosong');

            return;
        }
        // tidak dikirim langsung: antre, lalu notifikasi:kirim-wa mengirim satu pesan per menit agar nomor pengirim tidak diblokir WhatsApp
        DB::connection('notif')->table('notification_deliveries')->insert([
            'notification_id' => $n->id,
            'channel' => 'wa',
            'status' => 'pending',
            'target' => mb_substr($nomor[0], 0, 255),
            'attempts' => 0,
            'created_at' => now(),
        ]);
    }

    /** dipanggil notifikasi:kirim-wa: kirim satu pesan WA yang antre; mengembalikan false bila antrean kosong */
    public static function kirimWaBerikut(): bool
    {
        $tabel = DB::connection('notif')->table('notification_deliveries');
        $d = (clone $tabel)->where('channel', 'wa')->where('status', 'pending')->orderBy('id')->first();
        if (!$d) {
            return false;
        }
        // klaim baris ini; bila proses lain sudah mengambilnya, lewati
        if (!(clone $tabel)->where('id', $d->id)->where('attempts', $d->attempts)->update(['attempts' => $d->attempts + 1])) {
            return false;
        }
        $n = Notifikasi::find($d->notification_id);
        if (!$n) {
            (clone $tabel)->where('id', $d->id)->update(['status' => 'skipped', 'error' => 'Notifikasi sudah tidak ada']);

            return true;
        }
        $nomor = (string) $d->target;
        try {
            app(WhatsAppService::class)->sendMessage($nomor, "*{$n->judul}*\n{$n->isi}\n\nBuka: " . ($n->link ?: Hub::link($n->url)));
            (clone $tabel)->where('id', $d->id)->update([
                'status' => 'sent',
                'target' => substr($nomor, 0, 4) . '****' . substr($nomor, -3),
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // dicoba lagi pada menit berikutnya, menyerah setelah 3 percobaan
            $menyerah = $d->attempts + 1 >= 3;
            (clone $tabel)->where('id', $d->id)->update(['status' => $menyerah ? 'failed' : 'pending', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::warning('WA notifikasi gagal', ['notif' => $n->id, 'percobaan' => $d->attempts + 1, 'error' => $e->getMessage()]);
            // nomor tidak valid hanya masalah satu penerima; selebihnya (gateway mati, API key salah, sesi WA putus) perlu diketahui IT
            if ($menyerah && stripos($e->getMessage(), 'Invalid WhatsApp number') === false) {
                PeringatanWa::kirim('gagal', 'Pengiriman WhatsApp notifikasi gagal',
                    "Pesan WhatsApp notifikasi gagal terkirim setelah 3 percobaan. Gateway WhatsApp kemungkinan bermasalah.\n\n"
                    . "Notifikasi: {$n->judul} (#{$n->id}, event {$n->event})\nError: {$e->getMessage()}\n\n"
                    . 'Pesan lain tetap mengantre dan dicoba satu per menit. Daftar lengkap: menu Setting > Daftar Notifikasi.', 60);
            }
        }

        return true;
    }

    private static function webpush(Notifikasi $n): void
    {
        $cfg = config('notifikasi.webpush');
        if (empty($cfg['public_key']) || empty($cfg['private_key'])) {
            return;
        }
        $langganan = PushSubscription::where('employe_id', $n->recipient)->get();
        if ($langganan->isEmpty()) {
            return;
        }
        $fabrik = new HttpFactory();
        $push = new WebPush(
            ['VAPID' => ['subject' => $cfg['subject'], 'publicKey' => $cfg['public_key'], 'privateKey' => $cfg['private_key']]],
            [],
            new Client(['timeout' => 15]),
            $fabrik,
            $fabrik
        );
        $payload = json_encode([
            'id' => $n->id,
            'judul' => $n->judul,
            'isi' => $n->isi,
            'url' => $n->url ? '/' . ltrim($n->url, '/') : '/',
            'modul' => $n->modul,
        ], JSON_UNESCAPED_UNICODE);
        foreach ($langganan as $l) {
            $push->queueNotification(Subscription::create(['endpoint' => $l->endpoint, 'keys' => ['p256dh' => $l->p256dh, 'auth' => $l->auth]]), $payload);
        }
        $byEndpoint = $langganan->keyBy('endpoint');
        foreach ($push->flush() as $report) {
            $sub = $byEndpoint->get($report->getEndpoint());
            $host = parse_url($report->getEndpoint(), PHP_URL_HOST);
            if ($report->isSuccess()) {
                Hub::catatPengiriman($n->id, 'webpush', 'sent', $host);
                $sub?->update(['last_used_at' => now()]);
            } else {
                Hub::catatPengiriman($n->id, 'webpush', 'failed', $host, $report->getReason());
                // langganan kedaluwarsa (404/410) dibuang
                if ($report->isSubscriptionExpired()) {
                    $sub?->delete();
                }
            }
        }
    }

    // Disiapkan untuk aplikasi mobile: aktif bila FCM_AKTIF=true dan akun layanan Firebase sudah diisi
    private static function fcm(Notifikasi $n): void
    {
        if (!config('notifikasi.fcm.aktif') || !DeviceToken::where('employe_id', $n->recipient)->exists()) {
            return;
        }
        Hub::catatPengiriman($n->id, 'fcm', 'skipped', null, 'Pengirim FCM belum diimplementasikan (menunggu akun Firebase dan aplikasi mobile)');
    }
}
