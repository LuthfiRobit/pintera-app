<?php
// database/factories/KasusTugasFactory.php

namespace Database\Factories;

use App\Enums\StatusKasusTugas;
use App\Models\Kasus;
use App\Models\KasusTugas;
use Illuminate\Database\Eloquent\Factories\Factory;

class KasusTugasFactory extends Factory
{
    protected $model = KasusTugas::class;

    public function definition(): array
    {
        return [
            'kasus_id' => Kasus::factory(),
            'judul' => $this->faker->sentence(3),
            'instruksi' => $this->faker->sentence(),
            'frekuensi' => 'sekali',
            'mulai_pada' => now()->toDateString(),
            'batas_selesai_pada' => now()->addDays(7)->toDateString(),
            'status' => StatusKasusTugas::Ditugaskan,
        ];
    }
}
