<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use App\Services\GeoIp;
use Illuminate\Support\Facades\DB;

/**
 * Setting > Lokasi User (hanya role SuperAdmin): pengguna di peta menurut lokasi akses terakhirnya.
 * Live = sesi belum dicabut dan aktif dalam 5 menit terakhir. Selain itu ditampilkan di lokasi sesi terakhirnya.
 * Lokasi diperkirakan dari IP (GeoLite2 lokal), jadi hanya akurat sampai level kota.
 */
class LokasiUserController extends Controller
{
    private const LIVE_MENIT = 5;

    public function index()
    {
        $siap = GeoIp::siap();
        $batasLive = now()->subMinutes(self::LIVE_MENIT);

        // sesi terakhir tiap pengguna (90 hari terakhir)
        $sesi = UserSession::where('last_active_at', '>=', now()->subDays(90))
            ->orderByDesc('last_active_at')
            ->get()
            ->unique('user_id')
            ->values();

        $user = User::whereIn('id', $sesi->pluck('user_id'))->get(['id', 'employe_id'])->keyBy('id');
        $pegawai = DB::table('struktur_lengkap_oke')
            ->whereIn('employe_id', $user->pluck('employe_id')->filter()->all())
            ->get(['employe_id', 'first_name', 'position_name'])
            ->unique('employe_id')->keyBy('employe_id');

        $data = $sesi->map(function ($s) use ($user, $pegawai, $batasLive) {
            $employeId = $user[$s->user_id]->employe_id ?? null;
            $lokasi = GeoIp::cari($s->ip);

            return [
                'user_id' => (int) $s->user_id,
                'employe_id' => $employeId,
                'nama' => $pegawai[$employeId]->first_name ?? ($employeId ?: 'User #' . $s->user_id),
                'jabatan' => $pegawai[$employeId]->position_name ?? null,
                'ip' => $s->ip,
                'perangkat' => $s->perangkat,
                'aplikasi' => $s->aplikasi,
                'terakhir_aktif' => optional($s->last_active_at)->toDateTimeString(),
                'live' => $s->revoked_at === null && $s->last_active_at !== null && $s->last_active_at >= $batasLive,
                'lat' => $lokasi['lat'] ?? null,
                'lng' => $lokasi['lng'] ?? null,
                'kota' => $lokasi['kota'] ?? null,
                'negara' => $lokasi['negara'] ?? null,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'geoip_siap' => $siap,
            'batas_live_menit' => self::LIVE_MENIT,
            'diperbarui' => now()->toDateTimeString(),
            'data' => $data,
        ], 200);
    }
}
