<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Renbis extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('renbis')->insert([
            [
                'renbis' => 'Komersialisasi Perikanan',
                'tahun' => 2024,
                'rkap' => 120000000,
            ],
            [
                'renbis' => 'Revitalisasi ICS DKP Aceh',
                'tahun' => 2024,
                'rkap' => 1150000000,
            ],
            [
                'renbis' => 'Truk Box Berpendingin (JV)',
                'tahun' => 2024,
                'rkap' => 1720000000,
            ],
            [
                'renbis' => 'Pengolahan Kopi Arabika Gayo PEMA-JRG KSO (JO)',
                'tahun' => 2024,
                'rkap' => 400000000,
            ],
            [
                'renbis' => 'Perdagangan Pupuk',
                'tahun' => 2024,
                'rkap' => 190000000,
            ],
            [
                'renbis' => 'Pabrik Minyak Goreng',
                'tahun' => 2024,
                'rkap' => 200000000,
            ],
            [
                'renbis' => 'Peternakan Ayam Petelur',
                'tahun' => 2024,
                'rkap' => 134000000,
            ],
            [
                'renbis' => 'Alokasi Gas',
                'tahun' => 2024,
                'rkap' => 680000000,
            ],
            [
                'renbis' => 'Tambang (Batubara)',
                'tahun' => 2024,
                'rkap' => 300000000,
            ],
            [
                'renbis' => 'PI NSO 10%',
                'tahun' => 2024,
                'rkap' => 400000000,
            ],
            [
                'renbis' => 'Penyetoran 30% Saham PT. Perta Arun Gas',
                'tahun' => 2024,
                'rkap' => 1000000000,
            ],
            [
                'renbis' => 'Perkantoran Gedung WIKA',
                'tahun' => 2024,
                'rkap' => 2300000000,
            ],
            [
                'renbis' => 'Trading condensat',
                'tahun' => 2024,
                'rkap' => 300000000,
            ],
            [
                'renbis' => 'Efisiensi Energi di KEK Arun',
                'tahun' => 2024,
                'rkap' => 300000000,
            ],
            [
                'renbis' => 'Pembangkit Listrik Tenaga Bayu(PLTB)',
                'tahun' => 2024,
                'rkap' => 100000000,
            ],
            [
                'renbis' => 'Pabrik Asam Sulfat (H2SO4)',
                'tahun' => 2024,
                'rkap' => 1000000000,
            ],
            [
                'renbis' => 'JV WK Pase',
                'tahun' => 2024,
                'rkap' => 300000000,
            ],
            [
                'renbis' => 'Carbon Credit',
                'tahun' => 2024,
                'rkap' => 500000000,
            ],
            [
                'renbis' => 'Komersialiasi Sulfur',
                'tahun' => 2024,
                'rkap' => 720000000,
            ],
            [
                'renbis' => 'Revitalisasi Tanki Kondensat F-6104 Kilang Arun',
                'tahun' => 2024,
                'rkap' => 11720000000,
            ],
            [
                'renbis' => 'KSO PEMA-TELCO',
                'tahun' => 2024,
                'rkap' => 150000000,
            ],
            [
                'renbis' => 'Pengembangan BMN KEK ARUN',
                'tahun' => 2024,
                'rkap' => 300000000,
            ],
            [
                'renbis' => 'Pengelolaan Hutan Berimbang PEMA-CHL',
                'tahun' => 2024,
                'rkap' => 100000000,
            ],
        ]);
    }
}
