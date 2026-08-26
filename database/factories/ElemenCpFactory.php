<?php

namespace Database\Factories;

use App\Domains\Akademik\Models\ElemenCp;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElemenCpFactory extends Factory
{
    protected $model = ElemenCp::class;

    public function definition(): array
    {
        return [
            'kode' => $this->faker->unique()->slug(2),
            'nama' => $this->faker->words(3, true),
            'no_urut' => $this->faker->numberBetween(1, 10),
        ];
    }
}
