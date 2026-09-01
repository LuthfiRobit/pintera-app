<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\DataTransferObjects\SyncBillingConfigResult;
use App\Domains\Keuangan\Models\JenisTagihan;

class SyncJenisTagihanBillingConfigAction
{
    /**
     * @param  array<string, mixed>|null  $billing
     */
    public function execute(JenisTagihan $jenisTagihan, ?array $billing): SyncBillingConfigResult
    {
        $tarifLama = $this->snapshotTarif($jenisTagihan);
        $keringananLama = $this->snapshotKeringanan($jenisTagihan);

        $jenisTagihan->sasaranGrup()->delete();
        $jenisTagihan->keringananRules()->delete();

        if ($billing !== null) {
            foreach ($billing['sasaran'] ?? [] as $grupData) {
                $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
                foreach ($grupData['kriteria'] as $kriteriaData) {
                    $grup->kriteria()->create($kriteriaData);
                }
            }

            foreach ($billing['tarif'] ?? [] as $index => $grupData) {
                $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => $grupData['nominal'], 'priority' => $index + 1]);
                foreach ($grupData['kriteria'] as $kriteriaData) {
                    $grup->kriteria()->create($kriteriaData);
                }
            }

            foreach ($billing['keringanan'] ?? [] as $ruleData) {
                $jenisTagihan->keringananRules()->create($ruleData);
            }
        }

        $tarifBaru = $this->snapshotTarif($jenisTagihan->fresh());
        $keringananBaru = $this->snapshotKeringanan($jenisTagihan->fresh());

        return new SyncBillingConfigResult(
            tarifBerubah: $tarifLama !== $tarifBaru,
            keringananBerubah: $keringananLama !== $keringananBaru,
        );
    }

    private function snapshotTarif(JenisTagihan $jenisTagihan): string
    {
        $grups = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('priority')->get();

        return json_encode($grups->map(fn ($g) => [
            'nominal' => (float) $g->nominal,
            'priority' => $g->priority,
            'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->all(),
        ])->all());
    }

    private function snapshotKeringanan(JenisTagihan $jenisTagihan): string
    {
        $rules = $jenisTagihan->keringananRules()->orderBy('kategori_keringanan_id')->get();

        return json_encode($rules->map(fn ($r) => [
            'kategori_keringanan_id' => $r->kategori_keringanan_id,
            'tipe_potongan' => $r->tipe_potongan,
            'nilai' => (float) $r->nilai,
        ])->all());
    }
}
