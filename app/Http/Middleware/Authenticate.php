<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Token hanya berlaku bila versi password di dalamnya masih sama dengan password saat ini
     * (token lama di browser lain otomatis ditolak setelah password diganti).
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);

        $user = $this->auth->user();
        // akun pegawai nonaktif: token yang masih berlaku ditolak di semua route terautentikasi
        if ($user instanceof \App\Models\User && !\App\Models\User::masihAktif((int) $user->id)) {
            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Your account has been deactivated.',
            ], 401));
        }
        if ($user && method_exists($user, 'versiKredensial')) {
            try {
                // payload dibaca dari guard yang baru saja mengautentikasi (facade JWTAuth tidak berbagi token dengan guard)
                $payload = $this->auth->guard()->payload();
                $pv = $payload->get('pv');
                $sid = $payload->get('sid');
            } catch (\Throwable $e) {
                $pv = null;
                $sid = null;
            }
            if (!is_string($pv)) {
                // token terbitan sebelum fitur sesi (tanpa penanda versi): wajib login ulang
                throw new HttpResponseException(response([
                    'status' => false,
                    'message' => 'Sesi lama telah berakhir, silakan login kembali.',
                ], 401));
            }
            if (!hash_equals($user->versiKredensial(), $pv)) {
                throw new HttpResponseException(response([
                    'status' => false,
                    'message' => 'Sesi berakhir, password telah diubah. Silakan login kembali.',
                ], 401));
            }
            // perangkat/sesi yang sudah dikeluarkan lewat Kelola Akses tidak boleh dipakai lagi
            if ($user instanceof \App\Models\User) {
                if (!$sid || !\App\Models\UserSession::aktif((int) $sid, (int) $user->id)) {
                    throw new HttpResponseException(response([
                        'status' => false,
                        'message' => 'Sesi telah diakhiri, silakan login kembali.',
                    ], 401));
                }
                \App\Models\UserSession::catatAktivitas((int) $sid, $request);
            }
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // return $request->expectsJson() ? null : route('login');
        return $request->expectsJson() 
        ? 
            null 
        : 
            throw new HttpResponseException(response([
                "message" => 'Unauthenticated.'
            ], 401));;
    }
}
