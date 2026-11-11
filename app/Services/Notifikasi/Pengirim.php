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
            Hub::catatPengiriman($n->id, 'wa', 'skipped', null, 'WhatsApp tidak aktif (gateway mati atau pengiriman dijeda di menu Setting)');

            return;
        }
        $daftar = (array) config('notifikasi.wa.event');
        if (!in_array('*', $daftar, true) && !in_array($n->event, $daftar, true)) {
            Hub::catatPengiriman($n->id, 'wa', 'skipped', null, "Event {$n->event} tidak ada di daftar WA (config/notifikasi.php)");

            return;
        }
        $nomor = self::nomorWa((string) $n->recipient);
        if (!$nomor) {
            Hub::catatPengiriman($n->id, 'wa', 'skipped', null, 'Nomor HP penerima kosong');

            return;
        }
        // tidak dikirim langsung: antre, lalu notifikasi:kirim-wa mengirim satu pesan per menit agar nomor pengirim tidak diblokir WhatsApp
        DB::connection('notif')->table('notification_deliveries')->insert([
            'notification_id' => $n->id,
            'channel' => 'wa',
            'status' => 'pending',
            'target' => mb_substr($nomor, 0, 255),
            'attempts' => 0,
            'created_at' => now(),
        ]);
    }

    /** nomor HP pertama penerima, atau null bila kosong */
    public static function nomorWa(string $employeId): ?string
    {
        $hp = (string) Structure::where('employe_id', $employeId)->value('phone_number');
        $nomor = array_values(array_filter(preg_split('/[,;\/\s]+/', $hp), fn ($x) => preg_replace('/\D+/', '', $x) !== ''));

        return $nomor[0] ?? null;
    }

    /** Super Admin mengirim ulang satu pengiriman yang gagal/dilewati; mengembalikan pesan hasil atau melempar RuntimeException bila tidak bisa */
    public static function kirimUlang(int $deliveryId): string
    {
        $tabel = DB::connection('notif')->table('notification_deliveries');
        $d = (clone $tabel)->where('id', $deliveryId)->first();
        if (!$d || !in_array($d->status, ['failed', 'skipped'], true)) {
            throw new \RuntimeException('Hanya pengiriman yang gagal atau dilewati yang bisa dikirim ulang.');
        }
        $n = Notifikasi::find($d->notification_id);
        if (!$n) {
            throw new \RuntimeException('Notifikasi sudah tidak ada.');
        }
        if ($d->channel === 'wa') {
            if (!Pengaturan::waAktif()) {
                throw new \RuntimeException('WhatsApp sedang tidak aktif (gateway mati atau dijeda). Coba lagi setelah aktif.');
            }
            $nomor = self::nomorWa((string) $n->recipient);
            if (!$nomor) {
                throw new \RuntimeException('Nomor HP penerima masih kosong. Lengkapi dulu di data pegawai.');
            }
            // masuk antrean biasa (1 pesan per menit)
            (clone $tabel)->where('id', $d->id)->update(['status' => 'pending', 'target' => mb_substr($nomor, 0, 255), 'attempts' => 0, 'error' => null, 'sent_at' => null]);

            return 'Masuk antrean WhatsApp.';
        }
        if ($d->channel === 'webpush') {
            // baris lama diganti hasil kirim yang baru
            (clone $tabel)->where('id', $d->id)->delete();
            try {
                self::webpush($n);
            } catch (\Throwable $e) {
                Hub::catatPengiriman($n->id, 'webpush', 'failed', null, $e->getMessage());
            }

            return 'Push dikirim ulang, lihat hasilnya di daftar.';
        }

        throw new \RuntimeException('Kanal ini belum bisa dikirim ulang.');
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
        // library Web Push memicu peringatan "install GMP or BCMath" yang oleh Laravel diubah menjadi error dan membatalkan push;
        // peringatan itu hanya soal kecepatan, jadi diabaikan. Error lain tetap diteruskan.
        $lama = set_error_handler(function ($no, $pesan) use (&$lama) {
            if (str_contains((string) $pesan, 'GMP or BCMath')) {
                return true;
            }

            return $lama ? $lama(...func_get_args()) : false;
        });
        try {
            self::webpushKirim($n);
        } finally {
            restore_error_handler();
        }
    }

    private static function webpushKirim(Notifikasi $n): void
    {
        $cfg = config('notifikasi.webpush');
        if (empty($cfg['public_key']) || empty($cfg['private_key'])) {
            Hub::catatPengiriman($n->id, 'webpush', 'skipped', null, 'Kunci VAPID belum diisi di server (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY)');

            return;
        }
        $langganan = PushSubscription::where('employe_id', $n->recipient)->get();
        if ($langganan->isEmpty()) {
            Hub::catatPengiriman($n->id, 'webpush', 'skipped', null, 'Penerima belum mengaktifkan notifikasi push di browser');

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
