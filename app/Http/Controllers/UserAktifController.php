<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use Illuminate\Support\Facades\DB;

/**
 * Daftar pengguna yang sedang aktif (punya sesi login dengan aktivitas dalam beberapa menit terakhir).
 * Aktivitas dicatat paling sering sekali per 5 menit, jadi jendela 10 menit dipakai agar tidak berkedip.
 */
class UserAktifController extends Controller
{
    private const MENIT_AKTIF = 10;

    public function index()
    {
        $sesi = UserSession::whereNull('revoked_at')
            ->where('last_active_at', '>=', now()->subMinutes(self::MENIT_AKTIF))
            ->orderByDesc('last_active_at')
            ->get(['user_id', 'perangkat', 'aplikasi', 'last_active_at']);

        $perUser = $sesi->groupBy('user_id');
        $pegawai = DB::table('employees_hr')->whereIn('user_id', $perUser->keys()->all())
            ->get(['user_id', 'employe_id', 'first_name', 'last_name'])->keyBy('user_id');
        $divisi = DB::table('struktur_lengkap_oke')->whereIn('user_id', $perUser->keys()->all())
            ->pluck('organization_name', 'user_id');
        $jabatan = DB::table('struktur_lengkap_oke')->whereIn('user_id', $perUser->keys()->all())
            ->pluck('position_name', 'user_id');

        $daftar = $perUser->map(function ($baris, $userId) use ($pegawai, $divisi, $jabatan) {
            $p = $pegawai->get($userId);

            return [
                'employe_id' => $p->employe_id ?? null,
                'nama' => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) ?: 'Pengguna #' . $userId,
                'divisi' => $divisi[$userId] ?? null,
                'jabatan' => $jabatan[$userId] ?? null,
                'aplikasi' => $baris->pluck('aplikasi')->filter()->unique()->values()->all(),
                'jumlah_perangkat' => $baris->count(),
                'terakhir_aktif' => optional($baris->first()->last_active_at)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'menit' => self::MENIT_AKTIF,
            'jumlah' => $daftar->count(),
            'data' => $daftar,
        ], 200);
    }
}
