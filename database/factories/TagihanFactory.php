<?php

namespace Database\Factories;

use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tagihan> */
class TagihanFactory extends Factory
{
    protected $model = Tagihan::class;

    public function definition(): array
    {
        return [
            'pendaftaran_id' => Pendaftaran::factory(),
            'kategori' => 'pendaftaran',
            'total_tagihan' => 150000,
            'net_amount' => 150000,
            'status' => 'belum_bayar',
        ];
    }

    /**
     * Existing tests create Tagihan::factory()->create(['pendaftaran_id' => $x])
     * without knowing about tagihable_type/tagihable_id. This keeps those call
     * sites working unmodified by deriving the polymorphic columns from
     * whatever pendaftaran_id ends up on the model after factory state/overrides
     * are applied, exactly like TagihanGenerator now does for real PPDB rows.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Tagihan $tagihan) {
            if ($tagihan->pendaftaran_id && ! $tagihan->tagihable_type) {
                $tagihan->tagihable_type = Pendaftaran::class;
                $tagihan->tagihable_id = $tagihan->pendaftaran_id;
            }
        });
    }
}
