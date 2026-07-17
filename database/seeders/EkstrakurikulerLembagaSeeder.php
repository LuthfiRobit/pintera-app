<?php

namespace Database\Seeders;

use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class EkstrakurikulerLembagaSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedEkskul($smp, [
            ['Olahraga', 'Futsal', 4],
            ['Kepramukaan', 'Pramuka', 2],
            ['Keagamaan', 'Qiroah', 2],
        ]);

        $this->seedEkskul($sma, [
            ['Olahraga', 'Basket', 4],
            ['Kepramukaan', 'Paskibra', 3],
            ['Seni', 'Teater', 2],
        ]);
    }

    private function seedEkskul(Lembaga $lembaga, array $ekskulList): void
    {
        foreach ($ekskulList as [$jenis, $nama, $jam]) {
            EkstrakurikulerLembaga::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama_ekskul' => $nama],
                [
                    'jenis_ekskul' => $jenis,
                    'no_sk' => 'SK.'.random_int(100, 999).'/Yayasan/2024',
                    'tanggal_sk' => '2024-07-01',
                    'jam_per_minggu' => $jam,
                ]
            );
        }
    }
}
