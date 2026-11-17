<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Perkiraan lokasi dari alamat IP memakai database MaxMind GeoLite2-City lokal (file .mmdb di server).
 * IP tidak dikirim ke pihak luar. Hanya IP publik yang dicari; IP lokal/internal tidak punya lokasi.
 * Lokasi hanya perkiraan level kota, bukan posisi pasti perangkat.
 *
 * Pasang: composer require geoip2/geoip2, lalu letakkan GeoLite2-City.mmdb di storage/app/geoip/
 * (atau isi GEOIP_DB di .env dengan path file).
 */
class GeoIp
{
    private static $reader = null;

    public static function path(): string
    {
        return (string) config('services.geoip.db');
    }

    /** pustaka dan file database tersedia? */
    public static function siap(): bool
    {
        return class_exists(\GeoIp2\Database\Reader::class) && is_file(self::path());
    }

    /** @return array{lat:float,lng:float,kota:?string,negara:?string}|null */
    public static function cari(?string $ip): ?array
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || !self::siap()) {
            return null;
        }

        return Cache::remember('geoip:' . $ip, 86400, function () use ($ip) {
            try {
                self::$reader ??= new \GeoIp2\Database\Reader(self::path());
                $r = self::$reader->city($ip);
                if ($r->location->latitude === null || $r->location->longitude === null) {
                    return null;
                }

                return [
                    'lat' => (float) $r->location->latitude,
                    'lng' => (float) $r->location->longitude,
                    'kota' => $r->city->name ?: ($r->mostSpecificSubdivision->name ?: null),
                    'negara' => $r->country->name ?: null,
                ];
            } catch (\Throwable $e) {
                // IP tidak ada di database
                return null;
            }
        });
    }
}
