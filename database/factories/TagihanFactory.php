<?php

namespace Database\Factories;

use App\Models\Pendaftaran;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tagihan> */
class TagihanFactory extends Factory
{
    protected $model = Tagihan::class;

    public function definition(): array
    {
        return [
            'pendaftaran_id' => Pendaftaran::factory(),
            'kategori' => 'pendaftaran',
            'total_tagihan' => 150000,
            'status' => 'belum_bayar',
        ];
    }
}
