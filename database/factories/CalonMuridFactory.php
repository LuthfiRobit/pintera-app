<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalonMuridFactory extends Factory
{
    protected $model = CalonMurid::class;

    public function configure(): static
    {
        return $this->afterMaking(function (CalonMurid $calonMurid) {
            unset(
                $calonMurid->nama,
                $calonMurid->nama_lengkap,
                $calonMurid->nik,
                $calonMurid->nik_hash,
                $calonMurid->jenis_kelamin,
                $calonMurid->tempat_lahir,
                $calonMurid->tanggal_lahir,
                $calonMurid->agama,
                $calonMurid->no_telepon,
                $calonMurid->email_kontak
            );
        });
    }

    public function definition(): array
    {
        return [
            'person_id' => function (array $attributes) {
                $yayasanId = ! empty($attributes['yayasan_id']) && is_numeric($attributes['yayasan_id']) ? (int) $attributes['yayasan_id'] : null;
                $yayasanId ??= Yayasan::factory()->create()->id;

                return Person::factory()->create([
                    'yayasan_id' => $yayasanId,
                    'nik' => $attributes['nik'] ?? $this->faker->unique()->numerify('################'),
                    'nama_lengkap' => $attributes['nama_lengkap'] ?? $this->faker->name(),
                    'jenis_kelamin' => $attributes['jenis_kelamin'] ?? $this->faker->randomElement(['L', 'P']),
                    'tempat_lahir' => $attributes['tempat_lahir'] ?? $this->faker->city(),
                    'tanggal_lahir' => $attributes['tanggal_lahir'] ?? $this->faker->date(),
                    'agama' => $attributes['agama'] ?? 'Islam',
                ])->id;
            },
            'yayasan_id' => Yayasan::factory(),
        ];
    }
}
