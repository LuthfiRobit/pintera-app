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
        foreach (Lembaga::all() as $lembaga) {
            if (in_array($lembaga->bentuk_pendidikan, ['KB', 'TK'])) {
                $this->seedJenisTes($lembaga, ['Observasi Anak', 'Wawancara Orang Tua']);
            } elseif ($lembaga->bentuk_pendidikan === 'SD') {
                $this->seedJenisTes($lembaga, ['Observasi Kesiapan Sekolah', 'Wawancara Orang Tua', 'Tes Baca Al-Qur\'an']);
            } else {
                // SMP
                $this->seedJenisTes($lembaga, ['Tes Tulis', 'Wawancara', 'Tes Baca Al-Qur\'an']);
            }
        }
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
