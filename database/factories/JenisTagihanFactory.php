<?php

namespace Database\Factories;

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JenisTagihan> */
class JenisTagihanFactory extends Factory
{
    protected $model = JenisTagihan::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => 'Biaya Pendaftaran',
            'kategori' => 'pendaftaran',
            'bisa_dicicil' => false,
            'maks_cicilan' => null,
        ];
    }
}
