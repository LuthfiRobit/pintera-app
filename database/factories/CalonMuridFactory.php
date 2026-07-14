<?php

namespace Database\Factories;

use App\Models\CalonMurid;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalonMuridFactory extends Factory
{
    protected $model = CalonMurid::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'nik' => $this->faker->unique()->numerify('################'),
            'nama_lengkap' => $this->faker->name(),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->date(),
            'agama' => 'Islam',
        ];
    }
}
