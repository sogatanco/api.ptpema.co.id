<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TigaPuluh extends Model
{
    protected $connection='mysql4';
    protected $table = 'tigapuluhs';

    /** tabel tigapuluh_rentang (db/2026-11-23/tigapuluh_rentang.sql) sudah dibuat? */
    public static function adaTabelRentang(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::connection('mysql4')->hasTable('tigapuluh_rentang');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Rentang tanggal klaim: [['mulai' => 'Y-m-d', 'selesai' => 'Y-m-d'], ...] terurut.
     * Klaim lama (atau tabel rentang belum dibuat) menjadi satu rentang dari kolom mulai / selesai.
     */
    public function daftarRentang(): array
    {
        if (self::adaTabelRentang()) {
            $baris = \Illuminate\Support\Facades\DB::connection('mysql4')->table('tigapuluh_rentang')
                ->where('id_tigapuluh', $this->id)->orderBy('mulai')->get(['mulai', 'selesai']);
            if ($baris->isNotEmpty()) {
                return $baris->map(fn ($r) => ['mulai' => (string) $r->mulai, 'selesai' => (string) $r->selesai])->all();
            }
        }

        return [['mulai' => (string) $this->mulai, 'selesai' => (string) $this->selesai]];
    }

    /** simpan rentang (menggantikan yang lama) dan isi kolom mulai / selesai dengan tanggal paling awal dan paling akhir */
    public function simpanRentang(array $rentang): void
    {
        $rentang = array_values($rentang);
        $this->mulai = $rentang[0]['mulai'];
        $this->selesai = collect($rentang)->max('selesai');
        $this->save();

        if (self::adaTabelRentang()) {
            $db = \Illuminate\Support\Facades\DB::connection('mysql4')->table('tigapuluh_rentang');
            (clone $db)->where('id_tigapuluh', $this->id)->delete();
            (clone $db)->insert(array_map(fn ($r) => ['id_tigapuluh' => $this->id, 'mulai' => $r['mulai'], 'selesai' => $r['selesai']], $rentang));
        }
    }
}
