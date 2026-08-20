<?php

namespace Database\Factories;

use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolaJamFactory extends Factory
{
    protected $model = PolaJam::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => $this->faker->randomElement(['Kelas Rendah 1-3', 'Kelas Tinggi 4-6', 'Kelompok Bermain']),
        ];
    }
}
