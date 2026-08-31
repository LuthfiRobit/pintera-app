<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuruFactory extends Factory
{
    protected $model = Guru::class;

    public function definition(): array
    {
        return [
            'person_id' => function (array $attributes) {
                $yayasanId = null;
                if (! empty($attributes['lembaga_id']) && is_numeric($attributes['lembaga_id'])) {
                    $lembaga = Lembaga::withoutGlobalScopes()->find($attributes['lembaga_id']);
                    $yayasanId = $lembaga?->yayasan_id;
                }
                if (! $yayasanId && ! empty($attributes['user_id']) && is_numeric($attributes['user_id'])) {
                    $user = User::withoutGlobalScopes()->find($attributes['user_id']);
                    $yayasanId = $user?->yayasan_id;
                }
                $yayasanId ??= Yayasan::factory()->create()->id;

                $userId = ! empty($attributes['user_id']) && is_numeric($attributes['user_id']) ? (int) $attributes['user_id'] : null;

                return Person::factory()->create([
                    'yayasan_id' => $yayasanId,
                    'user_id' => $userId,
                    'nik' => $attributes['nik'] ?? $this->faker->unique()->numerify('################'),
                    'nama_lengkap' => $attributes['nama'] ?? $attributes['nama_lengkap'] ?? $this->faker->name(),
                    'jenis_kelamin' => $attributes['jenis_kelamin'] ?? $this->faker->randomElement(['L', 'P']),
                ])->id;
            },
            'user_id' => User::factory(),
            'lembaga_id' => Lembaga::factory(),
            'jenis_ptk' => $this->faker->randomElement(['guru_kelas', 'guru_mapel', 'kepala_sekolah', 'tenaga_administrasi']),
            'status_kepegawaian' => $this->faker->randomElement(['PNS', 'PPPK', 'GTY', 'PTY', 'Honorer']),
        ];
    }
}
