<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use App\Services\GeoIp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setting > Lokasi User (hanya role SuperAdmin): pengguna di peta menurut lokasi akses terakhirnya.
 * Live = sesi belum dicabut dan aktif dalam 5 menit terakhir. Selain itu ditampilkan di lokasi sesi terakhirnya.
 * Lokasi: dari browser bila pengguna sudah mengizinkan lokasi (kolom gps_* di user_sessions, db/user_sessions_gps.sql),
 * selain itu perkiraan dari IP (GeoIP lokal), yang hanya akurat sampai level kota.
 */
class LokasiUserController extends Controller
{
    private const LIVE_MENIT = 5;

    /** radius (meter) di sekitar titik kantor: laporan lokasi browser di dalamnya menandai IP-nya sebagai IP kantor */
    private const RADIUS_KANTOR_M = 400;

    private static function jarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /** IP ada di daftar KANTOR_IPS (alamat tunggal atau CIDR IPv4)? */
    private static function ipDalamDaftar(string $ip, array $daftar): bool
    {
        foreach ($daftar as $item) {
            if (!str_contains($item, '/')) {
                if ($ip === $item) {
                    return true;
                }
                continue;
            }
            [$dasar, $bit] = explode('/', $item, 2);
            $a = ip2long($ip);
            $b = ip2long($dasar);
            $bit = (int) $bit;
            if ($a === false || $b === false || $bit < 0 || $bit > 32) {
                continue;
            }
            $mask = $bit === 0 ? 0 : (-1 << (32 - $bit));
            if (($a & $mask) === ($b & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * IP publik => titik rata-rata lokasi browser terbaru (30 hari, akurasi <= 1 km) dari pengguna yang melapor dari IP itu.
     * Lebih tepat daripada tebakan database IP untuk jaringan yang dipakai bersama (kantor, NAT). Butuh minimal satu laporan.
     */
    private function petaKalibrasiIp(): array
    {
        if (!Schema::hasColumn('user_sessions', 'gps_lat')) {
            return [];
        }
        $baris = UserSession::whereNotNull('gps_lat')->whereNotNull('gps_lng')->whereNotNull('ip')
            ->where('gps_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->whereNull('gps_akurasi')->orWhere('gps_akurasi', '<=', 1000))
            ->orderByDesc('gps_at')
            ->get(['ip', 'gps_lat', 'gps_lng', 'gps_akurasi']);

        $peta = [];
        foreach ($baris->groupBy('ip') as $ip => $grup) {
            // IP internal/lokal tidak mewakili lokasi mana pun
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                continue;
            }
            $terbaru = $grup->take(10);
            $peta[$ip] = [
                'lat' => round($terbaru->avg('gps_lat'), 6),
                'lng' => round($terbaru->avg('gps_lng'), 6),
                'akurasi' => (int) max(100, round($terbaru->avg(fn ($r) => $r->gps_akurasi ?? 300))),
            ];
        }

        return $peta;
    }

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

        // kalibrasi: lokasi browser pengguna lain di IP yang sama (mis. jaringan kantor) dipakai untuk pengguna yang belum mengizinkan lokasi
        $kalibrasi = $this->petaKalibrasiIp();
        $kantor = (array) config('services.kantor');
        $ipKantor = array_values(array_filter(array_map('trim', explode(',', (string) ($kantor['ips'] ?? '')))));
        // IP yang laporan browsernya berada di sekitar kantor otomatis dianggap IP kantor
        foreach ($kalibrasi as $ip => $k) {
            if (self::jarakMeter($k['lat'], $k['lng'], (float) $kantor['lat'], (float) $kantor['lng']) <= self::RADIUS_KANTOR_M) {
                $ipKantor[] = $ip;
            }
        }

        $user = User::whereIn('id', $sesi->pluck('user_id'))->get(['id', 'employe_id'])->keyBy('id');
        $pegawai = DB::table('struktur_lengkap_oke')
            ->whereIn('employe_id', $user->pluck('employe_id')->filter()->all())
            ->get(['employe_id', 'first_name', 'position_name'])
            ->unique('employe_id')->keyBy('employe_id');

        $data = $sesi->map(function ($s) use ($user, $pegawai, $batasLive, $kalibrasi, $kantor, $ipKantor) {
            $employeId = $user[$s->user_id]->employe_id ?? null;
            // lokasi dari browser (pengguna sudah mengizinkan) didahulukan; tanpa itu perkiraan dari IP
            $gps = $s->gps_lat !== null && $s->gps_lng !== null;
            $diKantor = !$gps && $s->ip && self::ipDalamDaftar($s->ip, $ipKantor);
            $jaringan = (!$gps && !$diKantor && $s->ip) ? ($kalibrasi[$s->ip] ?? null) : null;
            $lokasi = ($gps || $diKantor || $jaringan) ? null : GeoIp::cari($s->ip);

            return [
                'user_id' => (int) $s->user_id,
                'employe_id' => $employeId,
                'nama' => $pegawai[$employeId]->first_name ?? ($employeId ?: 'User #' . $s->user_id),
                'jabatan' => $pegawai[$employeId]->position_name ?? null,
                'ip' => $s->ip,
                'perangkat' => $s->perangkat,
                'browser' => trim(explode(' · ', (string) $s->perangkat)[0] ?? '') ?: null,
                'sistem_operasi' => trim(explode(' · ', (string) $s->perangkat)[1] ?? '') ?: null,
                'jaringan' => $s->ip ? GeoIp::jaringan($s->ip) : null,
                'aplikasi' => $s->aplikasi,
                'terakhir_aktif' => optional($s->last_active_at)->toDateTimeString(),
                'live' => $s->revoked_at === null && $s->last_active_at !== null && $s->last_active_at >= $batasLive,
                'sumber' => $gps ? 'browser' : ($diKantor ? 'kantor' : ($jaringan ? 'jaringan' : ($lokasi ? 'ip' : null))),
                'akurasi' => $gps ? $s->gps_akurasi : ($diKantor ? null : ($jaringan['akurasi'] ?? null)),
                'lat' => $gps ? (float) $s->gps_lat : ($diKantor ? (float) $kantor['lat'] : ($jaringan['lat'] ?? ($lokasi['lat'] ?? null))),
                'lng' => $gps ? (float) $s->gps_lng : ($diKantor ? (float) $kantor['lng'] : ($jaringan['lng'] ?? ($lokasi['lng'] ?? null))),
                'kota' => $lokasi['kota'] ?? null,
                'negara' => $lokasi['negara'] ?? null,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'geoip_siap' => $siap,
            'asn_siap' => GeoIp::pathAsn() !== null,
            'batas_live_menit' => self::LIVE_MENIT,
            'kantor' => ['nama' => $kantor['nama'], 'lat' => (float) $kantor['lat'], 'lng' => (float) $kantor['lng']],
            'diperbarui' => now()->toDateTimeString(),
            'data' => $data,
        ], 200);
    }
}
