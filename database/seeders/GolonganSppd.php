<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GolonganSppd extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection('mysql4')->table('golongan_sppds')->insert([
            [
                'golongan' => 'Maincom',
                'id_pihak' => 1,
                'paraf' => 'Directors',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'Commissioner',
                'id_pihak' => 1,
                'paraf' => 'Directors',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'Presdir',
                'id_pihak' => 1,
                'paraf' => 'Commissioner',
                'sign'=> 'Maincom'
            ],
            [
                'golongan' => 'Presdir',
                'id_pihak' => 1,
                'paraf' => 'Commissioner',
                'sign'=> 'Maincom'
            ],
            [
                'golongan' => 'Director',
                'id_pihak' => 1,
                'paraf' => 'Director_UNK',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'Director',
                'id_pihak' => 1,
                'paraf' => 'Director_UNK',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'ManagerEks',
                'id_pihak' => 1,
                'paraf' => 'Director,Director_UNK',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'Manager',
                'id_pihak' => 1,
                'paraf' => 'Director,Director_UNK',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'Supervisor',
                'id_pihak' => 1,
                'paraf' => 'Manager',
                'sign'=> 'Director'
            ],
            [
                'golongan' => 'Staff',
                'id_pihak' => 1,
                'paraf' => 'Manager',
                'sign'=> 'Director'
            ],
            [
                'golongan' => 'Commitee',
                'id_pihak' => 1,
                'paraf' => 'Commissioners',
                'sign'=> 'Maincom'
            ],
            [
                'golongan' => 'Gol1',
                'id_pihak' => 2,
                'paraf' => 'Director',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'Gol2',
                'id_pihak' => 2,
                'paraf' => 'Director',
                'sign'=> 'Presdir'
            ],
            [
                'golongan' => 'Gol3',
                'id_pihak' => 2,
                'paraf' => 'Director',
                'sign'=> 'Presdir'
            ],
            
        ]);


    }
}
