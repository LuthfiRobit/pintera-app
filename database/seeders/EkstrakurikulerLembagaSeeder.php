<?php

namespace Database\Seeders;

use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class EkstrakurikulerLembagaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $ekskulList = match ($lembaga->bentuk_pendidikan) {
                'KB', 'TK' => [
                    ['Seni', 'Menggambar & Mewarnai', 2],
                    ['Olahraga', 'Bermain Bola Ceria', 2],
                ],
                'SD' => [
                    ['Keagamaan', 'Tahfidz Cilik', 3],
                    ['Kepramukaan', 'Pramuka', 2],
                    ['Seni', 'Nasyid', 2],
                ],
                default => [
                    ['Olahraga', 'Futsal', 4],
                    ['Kepramukaan', 'Pramuka', 2],
                    ['Keagamaan', 'Qiroah', 2],
                ],
            };

            $this->seedEkskul($lembaga, $ekskulList);
        }
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
