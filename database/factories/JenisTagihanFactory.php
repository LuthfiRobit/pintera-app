<?php

namespace Database\Factories;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JenisTagihan> */
class JenisTagihanFactory extends Factory
{
    protected $model = JenisTagihan::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => 'Biaya Pendaftaran',
            // Non-PPDB default: TagihanBillingGenerator (Sub-project 2a/2b-2) rejects
            // pendaftaran/daftar_ulang kategori, and most tests using this factory
            // exercise the billing engine without caring about kategori specifically.
            // Tests that DO need PPDB kategori pass it explicitly.
            'kategori' => 'lainnya',
            'bisa_dicicil' => false,
            'maks_cicilan' => null,
        ];
    }
}
