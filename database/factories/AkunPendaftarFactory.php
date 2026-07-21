<?php

namespace Database\Factories;

use App\Models\AkunPendaftar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AkunPendaftar>
 */
class AkunPendaftarFactory extends Factory
{
    protected $model = AkunPendaftar::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp_wa' => fake()->numerify('08##########'),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
