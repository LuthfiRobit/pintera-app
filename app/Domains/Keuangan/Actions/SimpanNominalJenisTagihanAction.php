<?php

namespace App\Domains\Keuangan\Actions;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use Illuminate\Support\Facades\DB;

class SimpanNominalJenisTagihanAction
{
    /**
     * @param  array<int|string, numeric>  $nominalData  Key: jalur_ppdb_id, Value: nominal
     */
    public function execute(JenisTagihan $jenisTagihan, array $nominalData): void
    {
        DB::transaction(function () use ($jenisTagihan, $nominalData) {
            foreach ($nominalData as $jalurId => $nominal) {
                if ($nominal !== null && $nominal !== '') {
                    NominalTagihanJalur::updateOrCreate(
                        [
                            'jenis_tagihan_id' => $jenisTagihan->id,
                            'jalur_ppdb_id'    => (int) $jalurId,
                        ],
                        ['nominal' => (float) $nominal]
                    );
                }
            }
        });
    }
}
