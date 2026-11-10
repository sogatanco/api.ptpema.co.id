<?php

namespace App\Services\Notifikasi;

use App\Models\Notif\DeviceToken;
use App\Models\Notif\Notifikasi;
use App\Models\Notif\PushSubscription;
use App\Models\Structure;
use App\Services\WhatsAppService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
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
        if (!config('notifikasi.wa.aktif') || !in_array($n->event, config('notifikasi.wa.event'), true)) {
            return;
        }
        $hp = (string) Structure::where('employe_id', $n->recipient)->value('phone_number');
        $nomor = array_values(array_filter(preg_split('/[,;\/\s]+/', $hp), fn ($x) => preg_replace('/\D+/', '', $x) !== ''));
        if (!$nomor) {
            Hub::catatPengiriman($n->id, 'wa', 'skipped', null, 'Nomor HP penerima kosong');

            return;
        }
        $pesan = "*{$n->judul}*\n{$n->isi}\n\nBuka: " . ($n->link ?: Hub::link($n->url));
        app(WhatsAppService::class)->sendMessage($nomor[0], $pesan);
        Hub::catatPengiriman($n->id, 'wa', 'sent', substr($nomor[0], 0, 4) . '****' . substr($nomor[0], -3));
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
