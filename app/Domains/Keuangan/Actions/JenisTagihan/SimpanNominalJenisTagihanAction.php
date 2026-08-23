<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\JalurPpdb;
use Illuminate\Support\Facades\DB;

class SimpanNominalJenisTagihanAction
{
    /**
     * @param  array<int|string, numeric>  $nominalData  Key: jalur_ppdb_id, Value: nominal
     */
    public function execute(JenisTagihan $jenisTagihan, array $nominalData): void
    {
        DB::transaction(function () use ($jenisTagihan, $nominalData) {
            $jalurIds = JalurPpdb::where('lembaga_id', $jenisTagihan->lembaga_id)->pluck('id');

            foreach ($nominalData as $jalurId => $nominal) {
                if (! $jalurIds->contains((int) $jalurId) || $nominal === null || $nominal === '') {
                    continue;
                }

                NominalTagihanJalur::updateOrCreate(
                    [
                        'jenis_tagihan_id' => $jenisTagihan->id,
                        'jalur_ppdb_id'    => (int) $jalurId,
                    ],
                    ['nominal' => (float) $nominal]
                );
            }
        });
    }
}
