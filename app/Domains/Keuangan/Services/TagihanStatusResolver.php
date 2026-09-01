<?php

namespace App\Domains\Keuangan\Services;

class TagihanStatusResolver
{
    public function resolve(float $paidAmount, float $netAmount, string $currentStatus): string
    {
        if ($currentStatus === 'dibatalkan') {
            return $currentStatus;
        }

        if ($paidAmount >= $netAmount) {
            return 'lunas';
        }

        return $paidAmount > 0 ? 'sebagian' : 'belum_bayar';
    }
}
