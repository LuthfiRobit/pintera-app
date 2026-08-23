<?php

namespace Database\Factories;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsesmenFactory extends Factory
{
    protected $model = Asesmen::class;

    public function definition(): array
    {
        return [
            'guru_id' => Guru::factory(),
            'kelas_id' => Kelas::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'semester_id' => Semester::factory(),
            'jenis' => JenisAsesmen::SumatifLingkupMateri,
            'judul' => 'Ulangan Harian ' . $this->faker->words(3, true),
            'tanggal' => $this->faker->date(),
        ];
    }
}
