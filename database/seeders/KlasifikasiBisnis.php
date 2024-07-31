<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KlasifikasiBisnis extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('klasifikasi_bisnis')->insert([
            [
                'k_bisnis' => 'Bisnis Berjalan',
            ],
            [
                'k_bisnis' => 'Bisnis Baru',
            ],
            [
                'k_bisnis' => 'Lainnya',
            ],
        ]);
    }
}
