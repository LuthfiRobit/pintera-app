<?php

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;

class SelesaikanTinjauanTagihanAction
{
    public function execute(Tagihan $tagihan): void
    {
        $tagihan->update([
            'perlu_ditinjau_ulang' => false,
            'alasan_perlu_ditinjau' => null,
        ]);
    }
}
