<?php

namespace Database\Factories;

use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class YayasanFactory extends Factory
{
    protected $model = Yayasan::class;

    public function definition(): array
    {
        return [
            'nama' => 'Yayasan '.$this->faker->word(),
        ];
    }
}
