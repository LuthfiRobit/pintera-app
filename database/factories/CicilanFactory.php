<?php

namespace Database\Factories;

use App\Models\Cicilan;
use App\Domains\Keuangan\Models\SkemaCicilan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cicilan> */
class CicilanFactory extends Factory
{
    protected $model = Cicilan::class;

    public function definition(): array
    {
        return [
            'skema_cicilan_id' => SkemaCicilan::factory(),
            'urutan' => 1,
            'nominal' => 1000000,
            'jatuh_tempo' => now()->addDays(30),
            'status' => 'belum_bayar',
        ];
    }
}
