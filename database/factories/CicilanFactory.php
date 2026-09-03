<?php

namespace Database\Factories;

use App\Domains\Keuangan\Models\Cicilan;
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
            // Auto-increment per skema_cicilan_id supaya membuat >1 Cicilan untuk skema yang
            // sama tidak tabrakan dengan UNIQUE KEY (skema_cicilan_id, urutan) tanpa perlu
            // override manual di setiap pemanggil.
            'urutan' => fn (array $attributes) => Cicilan::where('skema_cicilan_id', $attributes['skema_cicilan_id'])->max('urutan') + 1,
            'nominal' => 1000000,
            // Jatuh tempo bertahap mengikuti urutan termin (termin 1 = +1 bulan, termin 2 =
            // +2 bulan, dst), bukan tanggal statis yang sama untuk semua termin.
            'jatuh_tempo' => fn (array $attributes) => now()->addMonths($attributes['urutan']),
            'status' => 'belum_bayar',
        ];
    }
}
