<?php

namespace Database\Factories;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Guru;
use App\Models\Kelas;
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
            'subjek_type' => 'mata_pelajaran',
            'subjek_id' => fn () => MataPelajaran::factory()->create()->id,
            'semester_id' => Semester::factory(),
            'jenis' => JenisAsesmen::SumatifLingkupMateri,
            'judul' => 'Ulangan Harian ' . $this->faker->words(3, true),
            'tanggal' => $this->faker->date(),
        ];
    }

    public function elemenCp(): static
    {
        return $this->state(fn () => [
            'subjek_type' => 'elemen_cp',
            'subjek_id' => fn () => ElemenCp::factory()->create()->id,
        ]);
    }
}
