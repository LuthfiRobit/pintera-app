<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalPelajaranFactory extends Factory
{
    protected $model = JadwalPelajaran::class;

    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'jam_pelajaran_id' => JamPelajaran::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'guru_id' => Guru::factory(),
            'semester_id' => Semester::factory(),
        ];
    }
}
