<?php

namespace Database\Factories;

use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => $this->faker->randomElement(['Ganjil', 'Genap']),
            'urutan' => $this->faker->numberBetween(1, 2),
            'kode_dapodik' => $this->faker->numerify('##'),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addMonths(6),
            'status_aktif' => false,
        ];
    }
}
