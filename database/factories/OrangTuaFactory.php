<?php

namespace Database\Factories;

use App\Models\OrangTua;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrangTuaFactory extends Factory
{
    protected $model = OrangTua::class;

    public function configure(): static
    {
        return $this->afterCreating(function (OrangTua $orangTua) {
            $orangTua->user->update(['username' => $orangTua->nik]);
        });
    }

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nama_lengkap' => $this->faker->name(),
            'nik' => $this->faker->unique()->numerify('################'),
            'no_hp' => $this->faker->numerify('08##########'),
            'email' => $this->faker->optional()->safeEmail(),
            'alamat' => $this->faker->optional()->address(),
            'pekerjaan' => $this->faker->optional()->jobTitle(),
        ];
    }
}
