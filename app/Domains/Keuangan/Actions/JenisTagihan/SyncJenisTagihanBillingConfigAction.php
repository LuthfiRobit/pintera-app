<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;

class SyncJenisTagihanBillingConfigAction
{
    /**
     * Parse and synchronize sasaran grups, kriteria, and keringanan rules.
     *
     * @param  array<string, mixed>|null  $billing
     */
    public function execute(JenisTagihan $jenisTagihan, ?array $billing): void
    {
        $jenisTagihan->sasaranGrup()->delete();
        $jenisTagihan->keringananRules()->delete();

        if ($billing === null) {
            return;
        }

        foreach ($billing['sasaran'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['tarif'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => $grupData['nominal']]);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['keringanan'] ?? [] as $ruleData) {
            $jenisTagihan->keringananRules()->create($ruleData);
        }
    }
}
