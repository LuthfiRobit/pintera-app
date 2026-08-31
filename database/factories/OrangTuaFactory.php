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
                $yayasanId = ! empty($attributes['yayasan_id']) && is_numeric($attributes['yayasan_id']) ? (int) $attributes['yayasan_id'] : null;
                if (! $yayasanId && ! empty($attributes['user_id']) && is_numeric($attributes['user_id'])) {
                    $user = User::withoutGlobalScopes()->find($attributes['user_id']);
                    $yayasanId = $user?->yayasan_id;
                }
                $yayasanId ??= Yayasan::factory()->create()->id;

                $userId = ! empty($attributes['user_id']) && is_numeric($attributes['user_id']) ? (int) $attributes['user_id'] : null;

                return Person::factory()->create([
                    'yayasan_id' => $yayasanId,
                    'user_id' => $userId,
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
