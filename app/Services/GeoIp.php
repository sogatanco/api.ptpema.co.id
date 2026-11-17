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
    private static $readerAsn = null;

    /** file ASN (nama jaringan/ISP): GEOIP_ASN_DB, atau berkas *asn*.mmdb pertama di folder yang sama dengan database kota */
    public static function pathAsn(): ?string
    {
        $diatur = (string) config('services.geoip.asn_db');
        if ($diatur !== '') {
            return is_file($diatur) ? $diatur : null;
        }
        $ada = glob(dirname(self::path()) . DIRECTORY_SEPARATOR . '*asn*.mmdb') ?: [];

        return $ada[0] ?? null;
    }

    /** @return string seluler|isp|cloud */
    private static function jenisJaringan(string $org): string
    {
        if (preg_match('/telekomunikasi selular|telkomsel|indosat|ooredoo|excelcom|xl axiata|smartfren|smart telecom|hutchison|hutchinson|three|axis/i', $org)) {
            return 'seluler';
        }
        if (preg_match('/amazon|google|microsoft|azure|cloudflare|digitalocean|ovh|linode|vultr|alibaba|tencent|hetzner|oracle|datacamp|vpn|proxy|hosting|datacenter|data center/i', $org)) {
            return 'cloud';
        }

        return 'isp';
    }

    /** @return array{asn:int,org:string,jenis:string}|null  jaringan (ISP/operator) pemilik IP */
    public static function jaringan(?string $ip): ?array
    {
        $file = self::pathAsn();
        if (!$ip || !$file || !class_exists(\GeoIp2\Database\Reader::class) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return Cache::remember('geoip_asn:' . $ip, 86400, function () use ($ip, $file) {
            try {
                self::$readerAsn ??= new \GeoIp2\Database\Reader($file);
                $r = self::$readerAsn->asn($ip);
                $org = (string) $r->autonomousSystemOrganization;
                if ($org === '') {
                    return null;
                }

                return ['asn' => (int) $r->autonomousSystemNumber, 'org' => $org, 'jenis' => self::jenisJaringan($org)];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

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
