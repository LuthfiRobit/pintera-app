<?php

namespace Database\Factories;

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class KasusFactory extends Factory
{
    protected $model = Kasus::class;

    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'lembaga_id' => Lembaga::factory(),
            'kategori_masalah' => $this->faker->randomElement(['Perilaku', 'Akademik', 'Sosial']),
            'deskripsi' => $this->faker->sentence(),
            'status' => StatusKasus::Diajukan,
        ];
    }
}
