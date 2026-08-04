<?php

namespace Database\Factories;

use App\Models\Kasus;
use App\Models\KasusEvaluasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KasusEvaluasiFactory extends Factory
{
    protected $model = KasusEvaluasi::class;

    public function definition(): array
    {
        return [
            'kasus_id' => Kasus::factory(),
            'tanggal' => now(),
            'catatan' => $this->faker->sentence(),
            'keputusan' => 'lanjut',
            'dibuat_oleh_user_id' => User::factory(),
        ];
    }
}
