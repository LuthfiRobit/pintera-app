<?php

namespace Database\Factories;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\SkPpdb;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SkPpdbFactory extends Factory
{
    protected $model = SkPpdb::class;

    public function definition(): array
    {
        return [
            'gelombang_ppdb_id' => GelombangPpdb::factory(),
            'lembaga_id' => Lembaga::factory(),
            'nomor_sk' => '421.3/SK-PPDB.'.$this->faker->unique()->numberBetween(1, 999).'/2026',
            'tanggal_terbit' => now()->toDateString(),
            'diterbitkan_oleh_user_id' => User::factory(),
            'file_path' => 'sk/'.$this->faker->uuid().'.pdf',
        ];
    }
}
