<?php

namespace Database\Factories;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class GelombangPpdbFactory extends Factory
{
    protected $model = GelombangPpdb::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => 'Gelombang '.$this->faker->unique()->randomNumber(6),
            'tanggal_buka' => now()->subMonth(),
            'tanggal_tutup' => now()->addMonth(),
            'kuota' => $this->faker->numberBetween(20, 100),
        ];
    }
}
