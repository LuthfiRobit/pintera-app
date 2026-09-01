<?php

namespace Database\Factories;

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiswaKeringanan> */
class SiswaKeringananFactory extends Factory
{
    protected $model = SiswaKeringanan::class;

    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'kategori_keringanan_id' => KategoriKeringanan::factory(),
            'berlaku_dari' => now()->toDateString(),
            'berlaku_sampai' => null,
        ];
    }
}
