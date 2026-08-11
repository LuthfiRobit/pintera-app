<?php

namespace App\Services\Finance;

use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Tagihan;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutoAllocationEngine
{
    public function run(Wallet $wallet): void
    {
        // lockForUpdate to ensure wallet balance doesn't change concurrently during calculation
        DB::transaction(function () use ($wallet) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            
            $saldo = $wallet->balance;
            if ($saldo <= 0) {
                return; // Nothing to allocate
            }

            // Get active tagihan ordered by priority_score, then jatuh_tempo, then id
            $tagihans = $wallet->siswa->tagihan()
                ->join('jenis_tagihan', 'tagihan.jenis_tagihan_id', '=', 'jenis_tagihan.id')
                ->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
                ->orderBy('jenis_tagihan.priority_score', 'asc')
                ->orderBy('tagihan.jatuh_tempo', 'asc')
                ->orderBy('tagihan.id', 'asc')
                ->select('tagihan.*') // Ensure we only get tagihan columns
                ->lockForUpdate()
                ->get();

            if ($tagihans->isEmpty()) {
                return;
            }

            $allocated = [];
            $totalAllocatedAmount = 0;

            foreach ($tagihans as $tagihan) {
                if ($saldo <= 0) {
                    break;
                }

                $sisaTagihan = $tagihan->net_amount - $tagihan->paid_amount;
                $amountToPay = min($saldo, $sisaTagihan);

                if ($amountToPay > 0) {
                    $saldo -= $amountToPay;
                    $totalAllocatedAmount += $amountToPay;
                    
                    $allocated[] = [
                        'tagihan' => $tagihan,
                        'amount' => $amountToPay,
                    ];
                }
            }

            if ($totalAllocatedAmount > 0) {
                // Buat record pembayaran
                $pembayaran = Pembayaran::create([
                    'sumber' => 'admin',
                    'wallet_id' => $wallet->id,
                    'metode' => 'wallet_auto',
                    'is_auto_allocation' => true,
                    'status' => 'lunas',
                    'diverifikasi_pada' => now(),
                    'channel_reference' => 'AUTO-' . strtoupper(Str::random(10)),
                ]);

                // Debit wallet and create mutasi (within existing transaction — no nested lock)
                $wallet->debitWithinTransaction($totalAllocatedAmount, $pembayaran, 'Auto-allocation pembayaran tagihan');

                // Update tagihans and create pivot
                foreach ($allocated as $item) {
                    $tagihan = $item['tagihan'];
                    $amount = $item['amount'];

                    PembayaranTagihan::create([
                        'pembayaran_id' => $pembayaran->id,
                        'tagihan_id' => $tagihan->id,
                        'amount_allocated' => $amount,
                    ]);

                    $tagihan->paid_amount += $amount;
                    if ($tagihan->paid_amount >= $tagihan->net_amount) {
                        $tagihan->status = 'lunas';
                    } else {
                        $tagihan->status = 'sebagian';
                    }
                    $tagihan->save();
                }
            }
        });
    }
}
