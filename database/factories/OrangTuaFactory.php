<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Person;
use App\Models\OrangTua;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrangTuaFactory extends Factory
{
    protected $model = OrangTua::class;

    public function configure(): static
    {
        return $this->afterCreating(function (OrangTua $orangTua) {
            $user = User::withoutGlobalScopes()->find($orangTua->user_id);
            if ($user && $orangTua->nik) {
                $user->update(['username' => $orangTua->nik, 'email' => null]);
            }
        });
    }

    public function definition(): array
    {
        return [
            'person_id' => function (array $attributes) {
                $yayasan = Yayasan::factory()->create();

                return Person::factory()->create([
                    'yayasan_id' => $yayasan->id,
                    'user_id' => $attributes['user_id'] ?? null,
                    'nama_lengkap' => $attributes['nama_lengkap'] ?? $this->faker->name(),
                    'nik' => $attributes['nik'] ?? $this->faker->unique()->numerify('################'),
                    'no_hp' => $attributes['no_hp'] ?? $this->faker->numerify('08##########'),
                    'email' => $attributes['email'] ?? $this->faker->optional()->safeEmail(),
                    'alamat_jalan' => $attributes['alamat'] ?? $this->faker->optional()->address(),
                ])->id;
            },
            'user_id' => User::factory(),
            'pekerjaan' => $this->faker->optional()->jobTitle(),
        ];
    }
}
