<?php

namespace App\Services\Finance;

use App\Models\Pembayaran;
use Illuminate\Support\Facades\DB;

class PaymentAllocationService
{
    /**
     * Allocate payment amount to related bills (tagihan) and update their statuses.
     */
    public function allocate(Pembayaran $pembayaran): void
    {
        // Find all tagihans related to this payment via pembayaran_tagihan
        $pembayaranTagihans = $pembayaran->pembayaranTagihan()->with('tagihan')->get();

        foreach ($pembayaranTagihans as $pt) {
            $tagihan = $pt->tagihan;

            // Skip cancelled bills
            if ($tagihan->status === 'dibatalkan') {
                continue;
            }

            // Lock row for update just to be safe if within a transaction
            $lockedTagihan = $tagihan->lockForUpdate()->find($tagihan->id);
            if ($lockedTagihan->status === 'dibatalkan') {
                continue;
            }

            // Increase paid amount
            $lockedTagihan->paid_amount += $pt->amount_allocated;

            // Update status based on the new paid amount compared to net_amount
            if ($lockedTagihan->paid_amount >= $lockedTagihan->net_amount) {
                $lockedTagihan->status = 'lunas';
            } elseif ($lockedTagihan->paid_amount > 0) {
                $lockedTagihan->status = 'sebagian';
            }

            $lockedTagihan->save();
        }
    }
}
