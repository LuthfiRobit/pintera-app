<?php

namespace Database\Factories;

use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkemaCicilan> */
class SkemaCicilanFactory extends Factory
{
    protected $model = SkemaCicilan::class;

    public function definition(): array
    {
        return [
            'tagihan_id' => Tagihan::factory(),
            'jumlah_termin' => 3,
            'dibuat_oleh' => 'calon_siswa',
        ];
    }
}
