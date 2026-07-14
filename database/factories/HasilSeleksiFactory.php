<?php

namespace Database\Factories;

use App\Models\HasilSeleksi;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use Illuminate\Database\Eloquent\Factories\Factory;

class HasilSeleksiFactory extends Factory
{
    protected $model = HasilSeleksi::class;

    public function definition(): array
    {
        return [
            'pendaftaran_id' => Pendaftaran::factory(),
            'seleksi_ppdb_id' => SeleksiPpdb::factory(),
            'nilai' => $this->faker->randomFloat(2, 40, 100),
            'catatan' => null,
            'dinilai_oleh_user_id' => null,
            'dinilai_pada' => null,
        ];
    }
}
