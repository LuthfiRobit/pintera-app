<?php

namespace Database\Factories;

use App\Models\Asesmen;
use App\Models\NilaiSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class NilaiSiswaFactory extends Factory
{
    protected $model = NilaiSiswa::class;

    public function definition(): array
    {
        return [
            'asesmen_id' => Asesmen::factory(),
            'siswa_id' => Siswa::factory(),
            'skor' => $this->faker->randomFloat(2, 70, 100),
            'catatan' => $this->faker->optional(0.7)->sentence(5),
        ];
    }
}
