<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuruFactory extends Factory
{
    protected $model = Guru::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lembaga_id' => Lembaga::factory(),
            'nik' => $this->faker->unique()->numerify('################'),
            'nama' => $this->faker->name(),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'jenis_ptk' => $this->faker->randomElement(['guru_kelas', 'guru_mapel', 'kepala_sekolah', 'tenaga_administrasi']),
            'status_kepegawaian' => $this->faker->randomElement(['PNS', 'PPPK', 'GTY', 'PTY', 'Honorer']),
        ];
    }
}
