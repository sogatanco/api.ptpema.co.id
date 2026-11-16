<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Satu baris per login (perangkat/browser). Token JWT membawa id baris ini (klaim `sid`);
 * bila baris dicabut (revoked_at), token itu langsung ditolak walau belum kedaluwarsa.
 */
class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = ['user_id', 'ip', 'user_agent', 'perangkat', 'aplikasi', 'last_active_at', 'revoked_at'];

    protected $casts = ['last_active_at' => 'datetime', 'revoked_at' => 'datetime'];

    /** buat catatan login baru dari request yang sedang berjalan */
    public static function mulai(int $userId, Request $request): self
    {
        $ua = (string) $request->userAgent();
        $asal = parse_url((string) $request->headers->get('Origin'), PHP_URL_HOST);

        return self::create([
            'user_id' => $userId,
            'ip' => $request->ip(),
            'user_agent' => mb_substr($ua, 0, 512),
            'perangkat' => self::namaPerangkat($ua),
            'aplikasi' => $asal ? mb_substr($asal, 0, 60) : null,
            'last_active_at' => now(),
        ]);
    }

    /** sesi masih berlaku? hasilnya disimpan 30 detik agar tidak menambah query di setiap request */
    public static function aktif(int $id, int $userId): bool
    {
        $cari = fn () => self::where('id', $id)->where('user_id', $userId)->whereNull('revoked_at')->exists();
        try {
            return (bool) Cache::remember("sesi_aktif:$id", 30, $cari);
        } catch (\Throwable $e) {
            return $cari();
        }
    }

    /** catat aktivitas terakhir (paling sering sekali per 5 menit) */
    public static function catatAktivitas(int $id, Request $request): void
    {
        try {
            if (!Cache::add("sesi_tulis:$id", 1, 300)) {
                return;
            }
        } catch (\Throwable $e) {
            // cache tidak bisa ditulis: tetap catat
        }
        self::where('id', $id)->update(['last_active_at' => now(), 'ip' => $request->ip()]);
    }

    public function cabut(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
        self::lupakan($this->id);
    }

    /** cabut semua sesi milik pengguna (kecuali satu, bila diberikan) */
    public static function cabutSemua(int $userId, ?int $kecuali = null): void
    {
        $q = self::where('user_id', $userId)->whereNull('revoked_at');
        if ($kecuali) {
            $q->where('id', '!=', $kecuali);
        }
        foreach ($q->pluck('id') as $id) {
            self::lupakan((int) $id);
        }
        $q->update(['revoked_at' => now()]);
    }

    private static function lupakan(int $id): void
    {
        try {
            Cache::forget("sesi_aktif:$id");
        } catch (\Throwable $e) {
            // abaikan
        }
    }

    /** "Chrome 120 · Windows" dari User-Agent */
    public static function namaPerangkat(string $ua): string
    {
        $browser = 'Browser tidak dikenal';
        foreach ([
            'Edg' => 'Edge', 'OPR' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Firefox' => 'Firefox',
            'Chrome' => 'Chrome', 'CriOS' => 'Chrome', 'Safari' => 'Safari',
        ] as $kunci => $nama) {
            if (preg_match('/' . $kunci . '\/(\d+)/', $ua, $m)) {
                $browser = $nama . ' ' . $m[1];
                break;
            }
        }
        $os = 'Perangkat tidak dikenal';
        foreach ([
            'Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS',
            'Mac OS X' => 'macOS', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux',
        ] as $kunci => $nama) {
            if (stripos($ua, $kunci) !== false) {
                $os = $nama;
                break;
            }
        }

        return $browser . ' · ' . $os;
    }
}
