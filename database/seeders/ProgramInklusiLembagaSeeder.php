<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\ProgramInklusiLembaga;
use Illuminate\Database\Seeder;

class ProgramInklusiLembagaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            ProgramInklusiLembaga::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'kebutuhan_khusus' => 'Tunadaksa'],
                [
                    'no_sk' => 'SK.033/Yayasan/2021',
                    'tanggal_sk' => '2021-02-10',
                    'tmt' => '2021-07-01',
                    'tst' => null,
                    'keterangan' => 'Menyediakan akses ramah inklusi dan pendampingan.',
                ]
            );
        }
    }
}
