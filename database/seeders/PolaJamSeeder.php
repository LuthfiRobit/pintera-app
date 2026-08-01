<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\PolaJam;
use Illuminate\Database\Seeder;

class PolaJamSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            PolaJam::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => 'Pola Jam '.$lembaga->bentuk_pendidikan]
            );
        }
    }
}
