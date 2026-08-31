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

    public function configure(): static
    {
        return $this->afterMaking(function (Guru $guru) {
            unset(
                $guru->user_id,
                $guru->nama,
                $guru->nama_lengkap,
                $guru->nik,
                $guru->nik_hash,
                $guru->jenis_kelamin,
                $guru->tempat_lahir,
                $guru->tanggal_lahir,
                $guru->agama,
                $guru->kewarganegaraan,
                $guru->alamat_jalan,
                $guru->rt,
                $guru->rw,
                $guru->desa_kelurahan,
                $guru->kecamatan,
                $guru->kabupaten_kota,
                $guru->provinsi,
                $guru->kode_pos,
                $guru->no_hp,
                $guru->email
            );
        });
    }

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
                    'nik' => $attributes['nik'] ?? $this->faker->unique()->numerify('################'),
                    'nama_lengkap' => $attributes['nama'] ?? $attributes['nama_lengkap'] ?? $this->faker->name(),
                    'jenis_kelamin' => $attributes['jenis_kelamin'] ?? $this->faker->randomElement(['L', 'P']),
                    'email' => $attributes['email'] ?? $this->faker->safeEmail(),
                    'no_hp' => $attributes['no_hp'] ?? $this->faker->numerify('08##########'),
                ])->id;
            },
            'lembaga_id' => Lembaga::factory(),
            'jenis_ptk' => $this->faker->randomElement(['guru_kelas', 'guru_mapel', 'kepala_sekolah', 'tenaga_administrasi']),
            'status_kepegawaian' => $this->faker->randomElement(['PNS', 'PPPK', 'GTY', 'PTY', 'Honorer']),
        ];
    }
}
