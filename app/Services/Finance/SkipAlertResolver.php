<?php
// app/Services/Finance/SkipAlertResolver.php

namespace App\Services\Finance;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\Tagihan;

class SkipAlertResolver
{
    /**
     * Read-only replica of AutoAllocationEngine::run()'s FULL allocation walk
     * (priority ordering AND the partial-allocation logic), used ONLY to compute
     * what the banner should show — never touches the wallet or any tagihan row.
     * Does not call AutoAllocationEngine itself, per this plan's Global
     * Constraints (6a does not modify or invoke that engine's write path from
     * the dashboard).
     *
     * Semantics match AutoAllocationEngine exactly: a tagihan that would receive
     * ANY partial payment (amountToPay > 0) is NOT "skipped" — it would be marked
     * 'sebagian' by the real engine. Only a tagihan that would receive literally
     * $0 counts as skipped (zero-or-skip, not full-or-skip), and only the
     * highest-priority such tagihan is surfaced, matching
     * SaldoTidakCukupNotification's selection of $skippedTagihan->first().
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
        $allocatedIds = [];

        foreach ($tagihans as $tagihan) {
            if ($saldo <= 0) {
                break;
            }

            $sisaTagihan = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;
            $amountToPay = min($saldo, $sisaTagihan);

            if ($amountToPay > 0) {
                $saldo -= $amountToPay;
                $allocatedIds[] = $tagihan->id;
            }
        }

        $skipped = $tagihans->whereNotIn('id', $allocatedIds)->values();

        if ($skipped->isEmpty()) {
            return null;
        }

        $tagihan = $skipped->first();
        $selisih = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;

        $tagihan->setRelation(
            'jenisTagihan',
            $tagihan->jenisTagihan()->withoutGlobalScope(TenantScope::class)->first()
        );

        return ['tagihan' => $tagihan, 'selisih' => $selisih];
    }
}
