<?php

namespace Database\Factories;

use App\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class KaryawanFactory extends Factory
{
    protected $model = Karyawan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'yayasan_id' => Yayasan::factory(),
            'lembaga_id' => Lembaga::factory(),
            'jenis_karyawan_id' => JenisKaryawanMaster::factory(),
            'nama' => $this->faker->name(),
            'nik' => $this->faker->unique()->numerify('################'),
            'no_hp' => $this->faker->numerify('08##########'),
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
