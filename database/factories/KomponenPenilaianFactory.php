<?php

namespace Database\Factories;

use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class KomponenPenilaianFactory extends Factory
{
    protected $model = KomponenPenilaian::class;

    public function definition(): array
    {
        return [
            'subjek_type' => 'mata_pelajaran',
            'subjek_id' => fn () => MataPelajaran::factory()->create()->id,
            'semester_id' => Semester::factory(),
            'kode' => 'TP '.$this->faker->numberBetween(1, 9).'.'.$this->faker->numberBetween(1, 5),
            'deskripsi' => $this->faker->sentence(8),
            'kktp' => $this->faker->sentence(6),
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
