<?php

namespace Database\Factories;

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use Illuminate\Database\Eloquent\Factories\Factory;

class KalenderAkademikFactory extends Factory
{
    protected $model = KalenderAkademik::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => null,
            'tanggal' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'nama' => $this->faker->sentence(3),
            'tipe' => TipeKalenderAkademik::Libur->value,
            'keterangan' => null,
        ];
    }
}
