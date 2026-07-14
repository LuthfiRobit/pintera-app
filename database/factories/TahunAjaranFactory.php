<?php

namespace Database\Factories;

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class TahunAjaranFactory extends Factory
{
    protected $model = TahunAjaran::class;

    public function definition(): array
    {
        $tahunMulai = $this->faker->numberBetween(2020, 2099);

        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => $tahunMulai.'/'.($tahunMulai + 1),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addYear(),
            'status_aktif' => false,
        ];
    }
}
