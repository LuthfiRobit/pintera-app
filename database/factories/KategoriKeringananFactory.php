<?php

namespace Database\Factories;

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KategoriKeringanan> */
class KategoriKeringananFactory extends Factory
{
    protected $model = KategoriKeringanan::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => 'Yatim Piatu',
            'keterangan' => null,
        ];
    }
}
