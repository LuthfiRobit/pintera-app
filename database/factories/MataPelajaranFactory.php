<?php

namespace Database\Factories;

use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class MataPelajaranFactory extends Factory
{
    protected $model = MataPelajaran::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => $this->faker->randomElement(['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS', 'Pendidikan Agama']),
            'tipe' => TipeMataPelajaran::Mapel->value,
        ];
    }
}
