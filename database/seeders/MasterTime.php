<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterTime extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('master_times')->insert([
            [
                'start' => '00:00:00',
                'end' => '09:00:00',
                'rate_pergi' => 1,
                'rate_pulang' => 0.35,
            ],
            [
                'start' => '09:00:00',
                'end' => '17:00:00',
                'rate_pergi' => 0.7,
                'rate_pulang' => 0.7,
            ],
            [
                'start' => '17:00:00',
                'end' => '24:00:00',
                'rate_pergi' => 0.35,
                'rate_pulang' => 1,
            ],
        ]);
    }
}
