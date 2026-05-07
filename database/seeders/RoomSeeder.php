<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('meeting_rooms')->insert([
            [
                'room_name' => 'Growth Room',
                'capacity' => 12,
                'status' => 'available',
            ],
            [
                'room_name' => 'Harmony Room',
                'capacity' => 15,
                'status' => 'available',
            ],
            [
                'room_name' => 'Kopiah Room',
                'capacity' => 10,
                'status' => 'maintenance',
            ],
            [
                'room_name' => 'International Room',
                'capacity' => 10,
                'status' => 'available',
            ]
        ]);
    }
}
