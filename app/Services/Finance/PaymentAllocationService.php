<?php

namespace App\Services\Finance;

use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Notifications\Finance\PembayaranBerhasilNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentAllocationService
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

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
            $becameLunas = false;
            if ($lockedTagihan->paid_amount >= $lockedTagihan->net_amount) {
                $becameLunas = $lockedTagihan->status !== 'lunas';
                $lockedTagihan->status = 'lunas';
            } elseif ($lockedTagihan->paid_amount > 0) {
                $lockedTagihan->status = 'sebagian';
            }

            $lockedTagihan->save();

            if ($becameLunas) {
                $tagihanId = $lockedTagihan->id;
                $metode = $pembayaran->metode;

                DB::afterCommit(function () use ($tagihanId, $metode) {
                    $freshTagihan = Tagihan::with(['jenisTagihan', 'tagihable'])->find($tagihanId);
                    if ($freshTagihan === null || $freshTagihan->tagihable_type !== Siswa::class) {
                        return;
                    }

                    $siswa = $freshTagihan->tagihable;
                    $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
                    if ($kontakUtama !== null) {
                        try {
                            $this->dispatcher->send($kontakUtama, new PembayaranBerhasilNotification($freshTagihan, $metode));
                        } catch (\Throwable $e) {
                            Log::error('Gagal mengirim PembayaranBerhasilNotification: '.$e->getMessage());
                        }
                    }
                });
            }
        }
    }
}
