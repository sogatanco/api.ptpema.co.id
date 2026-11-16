<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses API (api/*, dapi/*, vapi/*) hanya dari aplikasi web yang diizinkan
 * (config cors.allowed_origins, default sys.pema.co.id dan hr.pema.co.id).
 * Browser selalu mengirim Origin (atau Referer) untuk permintaan lintas domain, jadi asal selain itu ditolak.
 * Catatan: header ini bisa dipalsukan oleh klien non-browser; pembatasan IP di web server tetap lapisan tambahan bila diperlukan.
 */
class BatasiAsalAkses
{
    /** Jalur server-ke-server yang punya autentikasi sendiri (middleware client) */
    private const PENGECUALIAN = ['api/client'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS') || !$request->is('api/*', 'dapi/*', 'vapi/*') || $request->is(...self::PENGECUALIAN)) {
            return $next($request);
        }

        $asal = $request->headers->get('Origin');
        if (!$asal) {
            $ref = $request->headers->get('Referer');
            $p = $ref ? parse_url($ref) : null;
            $asal = ($p && isset($p['scheme'], $p['host'])) ? $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '') : null;
        }

        $diizinkan = array_map(fn ($o) => rtrim(strtolower($o), '/'), (array) config('cors.allowed_origins', []));
        if (!$asal || !in_array(rtrim(strtolower($asal), '/'), $diizinkan, true)) {
            return response()->json(['status' => false, 'message' => 'Akses ditolak: asal permintaan tidak diizinkan.'], 403);
        }

        return $next($request);
    }
}
