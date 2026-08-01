<?php

namespace Database\Factories;

use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class MataPelajaranFactory extends Factory
{
    protected $model = MataPelajaran::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'kode' => strtoupper($this->faker->unique()->lexify('MP-????')),
            'nama' => $this->faker->randomElement(['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS', 'Pendidikan Agama']),
            'no_urut' => $this->faker->numberBetween(1, 10),
            'tipe' => TipeMataPelajaran::Mapel->value,
            'kelompok' => KelompokMataPelajaran::Umum->value,
            'status' => StatusMataPelajaran::Aktif->value,
        ];
    }
}
