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
    public function topupSisaJikaAda(Pembayaran $pembayaran): void
    {
        if (! in_array($pembayaran->topup_status, ['pending', 'failed'], true)) {
            return;
        }

        $porsiTagihan = $pembayaran->pembayaranTagihan()->sum('amount_allocated');
        $porsiTopup = (float) $pembayaran->amount - (float) $porsiTagihan;

        if ($porsiTopup <= 0) {
            return;
        }

        $siswa = $pembayaran->siswa;
        if (! $siswa || ! $siswa->wallet) {
            Log::error("Gagal topup dari pembayaran {$pembayaran->id}: Wallet siswa tidak ditemukan.");
            $pembayaran->update(['topup_status' => 'failed']);
            return;
        }

        try {
            $siswa->wallet->topup($porsiTopup, $pembayaran, "Top-up dari pembayaran {$pembayaran->metode} ({$pembayaran->id})");
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (\Exception $e) {
            Log::error("Gagal mengeksekusi topup dari pembayaran {$pembayaran->id}: ".$e->getMessage());
            $pembayaran->update(['topup_status' => 'failed']);
        }
    }
}
