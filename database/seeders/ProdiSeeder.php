<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProdiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $prodis = [
            ['kode_prodi' => '57201', 'nama_prodi' => 'Teknik Informatika'], 
            ['kode_prodi' => '57202', 'nama_prodi' => 'Sistem Informasi'],
            ['kode_prodi' => '26201', 'nama_prodi' => 'Teknik Industri'],
            ['kode_prodi' => '22201', 'nama_prodi' => 'Teknik Sipil'],
            ['kode_prodi' => '23201', 'nama_prodi' => 'Arsitektur'],
        ];

        DB::table('prodis')->insert($prodis);
    }
}
