<?php

namespace Database\Factories;

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class JalurPpdbFactory extends Factory
{
    protected $model = JalurPpdb::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => 'Jalur '.$this->faker->unique()->randomNumber(6),
            'status_aktif' => true,
        ];
    }
}
