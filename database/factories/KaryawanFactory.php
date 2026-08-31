<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class KaryawanFactory extends Factory
{
    protected $model = Karyawan::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Karyawan $karyawan) {
            unset(
                $karyawan->user_id,
                $karyawan->nama,
                $karyawan->nama_lengkap,
                $karyawan->nik,
                $karyawan->nik_hash,
                $karyawan->no_hp,
                $karyawan->email
            );
        });
    }

    public function definition(): array
    {
        return [
            'person_id' => function (array $attributes) {
                $yayasanId = ! empty($attributes['yayasan_id']) && is_numeric($attributes['yayasan_id']) ? (int) $attributes['yayasan_id'] : null;
                $yayasanId ??= auth()->user()?->yayasan_id
                    ?? auth()->user()?->lembaga?->yayasan_id
                    ?? Yayasan::first()?->id
                    ?? Yayasan::factory()->create()->id;
                $userId = ! empty($attributes['user_id']) && is_numeric($attributes['user_id'])
                    ? (int) $attributes['user_id']
                    : User::factory()->create(['yayasan_id' => $yayasanId])->id;

                return Person::factory()->create([
                    'yayasan_id' => $yayasanId,
                    'user_id' => $userId,
                    'nama_lengkap' => $attributes['nama'] ?? $this->faker->name(),
                    'nik' => $attributes['nik'] ?? $this->faker->unique()->numerify('################'),
                    'no_hp' => $attributes['no_hp'] ?? $this->faker->numerify('08##########'),
                    'email' => $attributes['email'] ?? $this->faker->safeEmail(),
                ])->id;
            },
            'yayasan_id' => Yayasan::factory(),
            'lembaga_id' => Lembaga::factory(),
            'jenis_karyawan_id' => JenisKaryawanMaster::factory(),
            'status_aktif' => 'aktif',
        ];
    }

    public function pool(): static
    {
        return $this->state(fn (array $attributes) => [
            'lembaga_id' => null,
        ]);
    }
}
