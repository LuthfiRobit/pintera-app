<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Person;
use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            'person_id' => function (array $attributes) {
                $yayasanId = null;
                if (! empty($attributes['lembaga_id']) && is_numeric($attributes['lembaga_id'])) {
                    $lembaga = Lembaga::withoutGlobalScopes()->find($attributes['lembaga_id']);
                    $yayasanId = $lembaga?->yayasan_id;
                }
                $yayasanId ??= auth()->user()?->yayasan_id
                    ?? auth()->user()?->lembaga?->yayasan_id
                    ?? Yayasan::first()?->id
                    ?? Yayasan::factory()->create()->id;

                $userId = ! empty($attributes['user_id']) && is_numeric($attributes['user_id']) ? (int) $attributes['user_id'] : null;

                return Person::factory()->create([
                    'yayasan_id' => $yayasanId,
                    'user_id' => $userId,
                    'nama_lengkap' => $attributes['nama_lengkap'] ?? $this->faker->name(),
                    'jenis_kelamin' => $attributes['jenis_kelamin'] ?? $this->faker->randomElement(['L', 'P']),
                    'tempat_lahir' => $attributes['tempat_lahir'] ?? $this->faker->city(),
                    'tanggal_lahir' => $attributes['tanggal_lahir'] ?? $this->faker->dateTimeBetween('-13 years', '-6 years')->format('Y-m-d'),
                    'agama' => $attributes['agama'] ?? 'Islam',
                ])->id;
            },
            'lembaga_id' => Lembaga::factory(),
            'kelas_id' => null,
            'calon_murid_id' => null,
            'pendaftaran_asal_id' => null,
            'sumber_data' => SumberDataSiswa::Manual->value,
            'nis' => $this->faker->unique()->numerify('2026####'),
            'nisn' => $this->faker->unique()->numerify('00########'),
            'status' => StatusSiswa::Aktif->value,
        ];
    }
}
