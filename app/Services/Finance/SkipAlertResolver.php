<?php
// app/Services/Finance/SkipAlertResolver.php

namespace App\Services\Finance;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\Tagihan;

class SkipAlertResolver
{
    /**
     * Read-only replica of AutoAllocationEngine::run()'s priority ordering and
     * allocation walk, used ONLY to compute what the banner should show — never
     * touches the wallet or any tagihan row. Does not call AutoAllocationEngine
     * itself, per this plan's Global Constraints (6a does not modify or invoke
     * that engine's write path from the dashboard).
     *
     * @return array{tagihan: Tagihan, selisih: float}|null
     */
    public function resolve(Siswa $siswa): ?array
    {
        $wallet = $siswa->wallet;

        if ($wallet === null) {
            return null;
        }

        $tagihans = $siswa->tagihan()
            ->withoutGlobalScope(TenantScope::class)
            ->join('jenis_tagihan', 'tagihan.jenis_tagihan_id', '=', 'jenis_tagihan.id')
            ->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
            ->orderBy('jenis_tagihan.priority_score', 'asc')
            ->orderBy('tagihan.jatuh_tempo', 'asc')
            ->orderBy('tagihan.id', 'asc')
            ->select('tagihan.*')
            ->get();

        if ($tagihans->isEmpty()) {
            return null;
        }

        $saldo = (float) $wallet->balance;

        foreach ($tagihans as $tagihan) {
            $sisaTagihan = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;

            if ($saldo >= $sisaTagihan) {
                // Fully covered by remaining balance — walk continues to the next
                // priority tagihan, same as AutoAllocationEngine's allocation order.
                $saldo -= $sisaTagihan;

                continue;
            }

            // First tagihan the remaining balance can't fully cover: this is the
            // one the dashboard banner should surface, with the shortfall being
            // how much more is needed on top of the current balance.
            $selisih = $sisaTagihan - $saldo;

            $tagihan->setRelation(
                'jenisTagihan',
                $tagihan->jenisTagihan()->withoutGlobalScope(TenantScope::class)->first()
            );

            return ['tagihan' => $tagihan, 'selisih' => $selisih];
        }

        return null;
    }
}
