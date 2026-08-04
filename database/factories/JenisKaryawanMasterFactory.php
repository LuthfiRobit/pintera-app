<?php

namespace Database\Factories;

use App\Models\JenisKaryawanMaster;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisKaryawanMasterFactory extends Factory
{
    protected $model = JenisKaryawanMaster::class;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->randomElement(['Psikolog', 'Konselor BK', 'Terapis', 'Pekerja Sosial']),
        ];
    }
}
