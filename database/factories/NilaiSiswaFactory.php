<?php

namespace Database\Factories;

use App\Models\Asesmen;
use App\Models\KomponenPenilaian;
use App\Models\NilaiSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class NilaiSiswaFactory extends Factory
{
    protected $model = NilaiSiswa::class;

    public function definition(): array
    {
        return [
            'asesmen_id' => Asesmen::factory(),
            'siswa_id' => Siswa::factory(),
            'komponen_penilaian_id' => KomponenPenilaian::factory(),
            'nilai_angka' => $this->faker->numberBetween(60, 100),
            'predikat' => null,
            'catatan' => $this->faker->optional(0.7)->sentence(5),
        ];
    }
}
