<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => $this->faker->unique()->numerify('#A'),
            'tingkat' => (string) $this->faker->numberBetween(1, 6),
            'wali_kelas_guru_id' => null,
        ];
    }
}
