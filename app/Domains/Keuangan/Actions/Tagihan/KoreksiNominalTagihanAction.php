<?php

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\TagihanStatusResolver;
use Illuminate\Support\Facades\DB;

class KoreksiNominalTagihanAction
{
    public function __construct(private readonly TagihanStatusResolver $statusResolver) {}

    public function execute(Tagihan $tagihan, float $totalTagihanBaru, float $discountAmountBaru): void
    {
        DB::transaction(function () use ($tagihan, $totalTagihanBaru, $discountAmountBaru) {
            $locked = Tagihan::lockForUpdate()->findOrFail($tagihan->id);

            if (! $locked->perlu_ditinjau_ulang) {
                abort(422, 'Tagihan ini tidak sedang ditinjau.');
            }

            $netAmountBaru = max(0, $totalTagihanBaru - $discountAmountBaru);

            $locked->total_tagihan = $totalTagihanBaru;
            $locked->discount_amount = $discountAmountBaru;
            $locked->net_amount = $netAmountBaru;
            $locked->status = $this->statusResolver->resolve((float) $locked->paid_amount, $netAmountBaru, $locked->status);
            $locked->perlu_ditinjau_ulang = false;
            $locked->alasan_perlu_ditinjau = null;
            $locked->save();
        });
    }
}
