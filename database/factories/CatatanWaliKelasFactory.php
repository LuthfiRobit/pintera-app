<?php

namespace Database\Factories;

use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatatanWaliKelasFactory extends Factory
{
    protected $model = CatatanWaliKelas::class;

    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'semester_id' => Semester::factory(),
            'catatan_sikap' => $this->faker->sentence(10),
        ];
    }
}
