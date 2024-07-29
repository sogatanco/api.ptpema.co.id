<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class CategoriSppd extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('categori_sppds')->insert([
            [
                'categori_sppd' => 'Dalam Provinsi',
            ],
            [
                'categori_sppd' => 'Luar Provinsi',
            ],
            [
                'categori_sppd' => 'Luar Negeri',
            ],
        ]);
    }
}
