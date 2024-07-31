<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PihakSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('pihaks')->insert([
            [
                'pihak_name' => 'Internal Perusahaan',
            ],
            [
                'pihak_name' => 'Stakeholder / Advisor',
            ]
        ]);
    }
}
