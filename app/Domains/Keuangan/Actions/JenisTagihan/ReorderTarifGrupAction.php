<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Domains\Keuangan\Models\Tagihan;
use App\Jobs\RecalculateTagihanNominalJob;
use Illuminate\Support\Facades\DB;

class ReorderTarifGrupAction
{
    /**
     * @param  array<int, int>  $urutanGrupId
     */
    public function execute(JenisTagihan $jenisTagihan, array $urutanGrupId): void
    {
        DB::transaction(function () use ($jenisTagihan, $urutanGrupId) {
            foreach ($urutanGrupId as $index => $grupId) {
                JenisTagihanSasaranGrup::where('id', $grupId)
                    ->where('jenis_tagihan_id', $jenisTagihan->id)
                    ->update(['priority' => $index + 1]);
            }
        });

        Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)
            ->whereNotIn('status', ['lunas', 'dibatalkan'])
            ->pluck('id')
            ->each(fn (int $tagihanId) => RecalculateTagihanNominalJob::dispatch($tagihanId));
    }
}
