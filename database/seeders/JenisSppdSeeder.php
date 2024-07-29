<?php

namespace Database\Seeders\Sppd;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JenisSppdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('pegawai')->insert(
            [
                'jenis_sppd' => 'Perjalan Dinas Khusus',
            ],
            [
                'jenis_sppd' => 'Perjalanan Dinas Terencana Biasa',
            ],
            [
                'jenis_sppd' => 'Perjalanan Dinas Terencana Tidak Biasa',
            ],
            [
                'jenis_sppd' => 'Perjalanan Dinas Tidak Terencana Biasa',
            ]
        );
    }
}
