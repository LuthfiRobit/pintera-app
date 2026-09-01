<?php

namespace Database\Factories;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;
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
     *
     * It then derives person_id the same way keuangan:backfill-tagihan-person-id
     * does for real data (Pendaftaran->calonMurid->person_id, or Siswa->person_id
     * directly), so factory-created rows satisfy the NOT NULL + FK constraint
     * without every call site having to pass person_id explicitly. person_id is
     * deliberately absent from definition() so this only fires when the caller
     * never mentioned person_id at all — a call site that explicitly overrides
     * ['person_id' => null] (e.g. to exercise the DB-level NOT NULL constraint)
     * must still get a real null through to the insert.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Tagihan $tagihan) {
            if ($tagihan->pendaftaran_id && ! $tagihan->tagihable_type) {
                $tagihan->tagihable_type = Pendaftaran::class;
                $tagihan->tagihable_id = $tagihan->pendaftaran_id;
            }

            if (! array_key_exists('person_id', $tagihan->getAttributes())) {
                $tagihan->person_id = match ($tagihan->tagihable_type) {
                    Pendaftaran::class => Pendaftaran::find($tagihan->tagihable_id)?->calonMurid?->person_id,
                    Siswa::class => Siswa::withoutGlobalScopes()->find($tagihan->tagihable_id)?->person_id,
                    default => null,
                };
            }
        });
    }
}
