<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SumberBiaya extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('sumber_biayas')->insert([
            [
                'sumber_biaya' => 'PEMA',
            ],
            [
                'sumber_biaya' => 'Sponsor',
            ],
            [
                'sumber_biaya' => 'Sharing',
            ],
        ]);
    }
}
