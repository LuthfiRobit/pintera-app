<?php

namespace Database\Factories;

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisTesMasterFactory extends Factory
{
    protected $model = JenisTesMaster::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => 'Tes '.$this->faker->unique()->word(),
            'deskripsi' => $this->faker->sentence(),
        ];
    }
}
