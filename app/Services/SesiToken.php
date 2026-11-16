<?php

namespace App\Services;

use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class SesiToken
{
    /** klaim token yang terakhir dibaca oleh pemilik() (dipakai refresh untuk meneruskan id sesi) */
    public static array $klaim = [];

    /**
     * Masa berlaku token menurut asal permintaan: aplikasi HR memakai token 1 hari (config jwt.ttl_hr),
     * aplikasi lain memakai jwt.ttl. Dipanggil sebelum token dibuat atau diperpanjang.
     */
    public static function terapkanTtl(Request $request): void
    {
        $asal = rtrim(strtolower((string) $request->headers->get('Origin')), '/');
        $hr = array_map(fn ($o) => rtrim(strtolower(trim($o)), '/'), explode(',', (string) config('jwt.hr_origins')));
        if ($asal !== '' && in_array($asal, $hr, true)) {
            JWTAuth::factory()->setTTL((int) config('jwt.ttl_hr'));
        }
    }

    /**
     * Pemilik token untuk proses refresh. Token yang sudah kedaluwarsa tetap bisa dibaca (hanya tanda tangan yang diperiksa),
     * tetapi token dari sebelum password diganti ditolak (null).
     *
     * @param class-string $model User atau UserVendor
     */
    public static function pemilik(Request $request, string $model)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return null;
            }
            $klaim = JWTAuth::manager()->getJWTProvider()->decode($token);
            $pemilik = $model::find($klaim['sub'] ?? null);
            self::$klaim = $klaim;

            return $pemilik && isset($klaim['pv']) && is_string($klaim['pv']) && hash_equals($pemilik->versiKredensial(), $klaim['pv']) ? $pemilik : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function masihSesuai(Request $request, string $model): bool
    {
        return self::pemilik($request, $model) !== null;
    }
}
