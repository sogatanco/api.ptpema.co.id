<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BasePosition extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('base_positions')->insert([
            [
                'base_name' => 'Supporting Staff',
            ],
            [
                'base_name' => 'Komisaris Utama',
            ],
            [
                'base_name' => 'Komisaris',
            ],
            [
                'base_name' => 'Direktur Utama',
            ],
            [
                'base_name' => 'Direktur',
            ],
            [
                'base_name' => 'Komite',
            ],
            [
                'base_name' => 'Manajer Eksekutif',
            ],
            [
                'base_name' => 'Manajer',
            ],
            [
                'base_name' => 'Supervisor',
            ],
            [
                'base_name' => 'Staff',
            ],
            [
                'base_name' => 'Supporting Staff',
            ],
        ]); 
    }
}
