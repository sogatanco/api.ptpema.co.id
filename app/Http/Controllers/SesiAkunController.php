<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Kelola akses: pengguna melihat perangkat (IP, browser) yang sedang login ke akunnya dan bisa mengeluarkannya.
 */
class SesiAkunController extends Controller
{
    /** sesi dianggap sudah tidak aktif bila tidak dipakai lebih lama dari masa refresh */
    private function batasHari(): int
    {
        return max(1, (int) ceil(((int) config('jwt.refresh_ttl')) / 1440));
    }

    private function sidSaatIni(): int
    {
        try {
            return (int) Auth::payload()->get('sid');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function index()
    {
        $saatIni = $this->sidSaatIni();
        $daftar = UserSession::where('user_id', Auth::id())
            ->whereNull('revoked_at')
            ->where('last_active_at', '>=', now()->subDays($this->batasHari()))
            ->orderByDesc('last_active_at')
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'ip' => $s->ip,
                'perangkat' => $s->perangkat,
                'aplikasi' => $s->aplikasi,
                'login_at' => optional($s->created_at)->toDateTimeString(),
                'terakhir_aktif' => optional($s->last_active_at)->toDateTimeString(),
                'saat_ini' => (int) $s->id === $saatIni,
            ]);

        return response()->json(['status' => true, 'data' => $daftar->values()], 200);
    }

    /**
     * Lokasi dari browser untuk sesi ini. Hanya dikirim browser yang penggunanya sudah mengizinkan lokasi;
     * dipakai Setting > Lokasi User (tanpa ini peta memakai perkiraan lokasi dari IP).
     */
    public function lokasi(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'akurasi' => 'nullable|numeric|min:0|max:1000000',
        ]);
        $sid = $this->sidSaatIni();
        if (!$sid || !\Illuminate\Support\Facades\Schema::hasColumn('user_sessions', 'gps_lat')) {
            return response()->json(['status' => false], 200);
        }
        UserSession::where('id', $sid)->where('user_id', Auth::id())->whereNull('revoked_at')->update([
            'gps_lat' => round((float) $data['lat'], 6),
            'gps_lng' => round((float) $data['lng'], 6),
            'gps_akurasi' => isset($data['akurasi']) ? (int) round($data['akurasi']) : null,
            'gps_at' => now(),
        ]);

        return response()->json(['status' => true], 200);
    }

    public function keluarkan(int $id)
    {
        $sesi = UserSession::where('id', $id)->where('user_id', Auth::id())->whereNull('revoked_at')->first();
        if (!$sesi) {
            return response()->json(['status' => false, 'message' => 'Sesi tidak ditemukan.'], 404);
        }
        $sesi->cabut();

        return response()->json([
            'status' => true,
            'message' => 'Perangkat dikeluarkan.',
            'saat_ini' => (int) $sesi->id === $this->sidSaatIni(),
        ], 200);
    }

    public function keluarkanLainnya()
    {
        UserSession::cabutSemua((int) Auth::id(), $this->sidSaatIni() ?: null);

        return response()->json(['status' => true, 'message' => 'Semua perangkat lain dikeluarkan.'], 200);
    }
}
