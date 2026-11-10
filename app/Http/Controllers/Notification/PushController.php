<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\Notif\DeviceToken;
use App\Models\Notif\Notifikasi;
use App\Models\Notif\PushSubscription;
use App\Services\Notifikasi\Hub;
use App\Services\Notifikasi\Pengirim;
use Illuminate\Http\Request;

/** Langganan Web Push dan token perangkat FCM milik pegawai yang sedang login */
class PushController extends Controller
{
    public function kunci()
    {
        $kunci = config('notifikasi.webpush.public_key');

        return response()->json(['status' => (bool) ($kunci && config('notifikasi.aktif')), 'public_key' => $kunci]);
    }

    public function langganan(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|url|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $request->endpoint)],
            [
                'employe_id' => Employe::employeId(),
                'endpoint' => $request->endpoint,
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['status' => true]);
    }

    public function berhenti(Request $request)
    {
        $request->validate(['endpoint' => 'required|string|max:2000']);
        PushSubscription::where('endpoint_hash', hash('sha256', $request->endpoint))->where('employe_id', Employe::employeId())->delete();

        return response()->json(['status' => true]);
    }

    /** token FCM dari aplikasi mobile (disiapkan; pengiriman FCM aktif setelah akun Firebase tersedia) */
    public function perangkat(Request $request)
    {
        $request->validate(['platform' => 'required|in:android,ios,web', 'token' => 'required|string|max:4096']);
        DeviceToken::updateOrCreate(
            ['token_hash' => hash('sha256', $request->token)],
            ['employe_id' => Employe::employeId(), 'platform' => $request->platform, 'token' => $request->token, 'last_used_at' => now()]
        );

        return response()->json(['status' => true]);
    }

    /** kirim notifikasi uji ke perangkat milik sendiri */
    public function uji()
    {
        $me = Employe::employeId();
        $n = Notifikasi::create([
            'modul' => 'sistem',
            'event' => 'sistem.uji',
            'recipient' => $me,
            'judul' => 'Tes notifikasi',
            'isi' => 'Notifikasi push berfungsi di perangkat ini.',
            'url' => '/',
            'link' => Hub::link('/'),
            'created_at' => now(),
        ]);
        Pengirim::kirimSemua($n->id);

        return response()->json(['status' => true]);
    }
}
