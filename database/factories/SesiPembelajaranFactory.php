<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\SesiPembelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class SesiPembelajaranFactory extends Factory
{
    protected $model = SesiPembelajaran::class;

    public function definition(): array
    {
        return [
            'jadwal_pelajaran_id' => null,
            'kelas_id' => Kelas::factory(),
            'guru_id' => Guru::factory(),
            'mata_pelajaran_id' => null,
            'tanggal' => now()->format('Y-m-d'),
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:35',
            'materi' => null,
            'status' => 'terlaksana',
        ];
    }
}
