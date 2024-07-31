<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BiayaSppd extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('biaya_sppds')->insert([
            // komut
            [
                'id_golongan' => 1,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 1,
                'id_category' => 2,
                'tiket' => 500000,
                'um' => 500000,
                'us' => 00000,
                'tr' => 500000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 1,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],
            // komisaris
            [
                'id_golongan' => 2,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 2,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 500000,
                'us' => 500000,
                'tr' => 500000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 2,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // dirut
            [
                'id_golongan' => 3,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 3,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 500000,
                'us' => 500000,
                'tr' => 500000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 3,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // direksi
            [
                'id_golongan' => 4,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 4,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 500000,
                'us' => 500000,
                'tr' => 500000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 4,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // manager eks
            [
                'id_golongan' => 5,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 300000,
                'us' => 300000,
                'tr' => 300000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 5,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 5,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // manager
            [
                'id_golongan' => 6,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 300000,
                'us' => 300000,
                'tr' => 300000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 6,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 6,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // supervisor
            [
                'id_golongan' => 7,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 250000,
                'us' => 300000,
                'tr' => 250000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 7,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 350000,
                'us' => 300000,
                'tr' => 350000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 7,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // staff
            [
                'id_golongan' => 8,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 200000,
                'us' => 300000,
                'tr' => 200000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 8,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 300000,
                'us' => 300000,
                'tr' => 300000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 8,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // committee
            [
                'id_golongan' => 9,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 300000,
                'us' => 300000,
                'tr' => 300000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 9,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 9,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // gol 1
            [
                'id_golongan' => 10,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 10,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 500000,
                'us' => 500000,
                'tr' => 500000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 10,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // gol 2
            [
                'id_golongan' => 11,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 300000,
                'us' => 300000,
                'tr' => 300000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 11,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 400000,
                'us' => 400000,
                'tr' => 400000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 11,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // gol 3
            [
                'id_golongan' => 12,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 200000,
                'us' => 300000,
                'tr' => 200000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 12,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 250000,
                'us' => 300000,
                'tr' => 300000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 12,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

            // supporting
            [
                'id_golongan' => 13,
                'id_category' => 1,
                'tiket' => 0,
                'um' => 150000,
                'us' => 300000,
                'tr' => 200000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 13,
                'id_category' => 2,
                'tiket' => 0,
                'um' => 250000,
                'us' => 300000,
                'tr' => 300000,
                'hotel' => 0
            ],
            [
                'id_golongan' => 13,
                'id_category' => 3,
                'tiket' => 0,
                'um' => 0,
                'us' => 0,
                'tr' => 0,
                'hotel' => 0
            ],

        ]);
    }
}
