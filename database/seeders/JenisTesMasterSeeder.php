<?php
// database/seeders/JenisTesMasterSeeder.php

namespace Database\Seeders;

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class JenisTesMasterSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedJenisTes($smp, ['Tes Tulis', 'Wawancara', 'Tes Baca Al-Qur\'an']);
        $this->seedJenisTes($sma, ['Tes Tulis', 'Tes Wawancara', 'Tes Potensi Akademik']);
    }

    private function seedJenisTes(Lembaga $lembaga, array $namaList): void
    {
        foreach ($namaList as $nama) {
            JenisTesMaster::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => $nama],
                ['deskripsi' => "Seleksi berupa {$nama} yang dinilai oleh tim penerimaan murid baru."]
            );
        }
    }
}
