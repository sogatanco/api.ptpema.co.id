<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DasarSppd extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('dasar_sppds')->insert([
            [
               'dasar_sppd' => 'Perintah',
            ],
            [
               'dasar_sppd' => 'Undangan',
            ]          
        ]);
    }
}
