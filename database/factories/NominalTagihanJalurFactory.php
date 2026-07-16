<?php

namespace Database\Factories;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NominalTagihanJalur> */
class NominalTagihanJalurFactory extends Factory
{
    protected $model = NominalTagihanJalur::class;

    public function definition(): array
    {
        return [
            'jenis_tagihan_id' => JenisTagihan::factory(),
            'jalur_ppdb_id' => JalurPpdb::factory(),
            'nominal' => 150000,
        ];
    }
}
