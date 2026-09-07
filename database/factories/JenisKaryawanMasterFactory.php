<?php

namespace Database\Factories;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisKaryawanMasterFactory extends Factory
{
    protected $model = JenisKaryawanMaster::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'nama' => $this->faker->unique()->randomElement(['Psikolog', 'Konselor BK', 'Terapis', 'Pekerja Sosial']),
            'is_konselor' => false,
        ];
    }

    public function konselor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_konselor' => true,
        ]);
    }
}
