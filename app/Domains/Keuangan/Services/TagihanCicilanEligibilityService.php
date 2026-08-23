<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\Tagihan;

class TagihanCicilanEligibilityService
{
    /**
     * A tagihan can bundle multiple jenis_tagihan (line items) with different
     * bisa_dicicil rules -- offering installment is allowed if ANY item is
     * cicilable, and the safe max termin count is the smallest maks_cicilan
     * among the cicilable items (never lets the whole invoice cicil beyond
     * what any single cicilable item's own rule allows).
     */
    public function bisaDicicil(Tagihan $tagihan): bool
    {
        return $tagihan->item()->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))->exists();
    }

    public function maksCicilan(Tagihan $tagihan): ?int
    {
        return $tagihan->item()
            ->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))
            ->with('jenisTagihan')
            ->get()
            ->min(fn ($item) => $item->jenisTagihan->maks_cicilan);
    }
}