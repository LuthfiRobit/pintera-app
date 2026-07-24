<?php

namespace Database\Factories;

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'kelas_id' => null,
            'calon_murid_id' => null,
            'pendaftaran_asal_id' => null,
            'sumber_data' => SumberDataSiswa::Manual->value,
            'nis' => $this->faker->unique()->numerify('2026####'),
            'nisn' => $this->faker->unique()->numerify('00########'),
            'nama_lengkap' => $this->faker->name(),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->dateTimeBetween('-13 years', '-6 years')->format('Y-m-d'),
            'agama' => 'Islam',
            'status' => StatusSiswa::Aktif->value,
        ];
    }
}
