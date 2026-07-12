<?php

namespace Database\Seeders;

use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class YayasanSeeder extends Seeder
{
    public function run(): void
    {
        Yayasan::firstOrCreate(
            ['nama' => 'Yayasan Pintera'],
            ['npwp_yayasan' => null]
        );
    }
}
