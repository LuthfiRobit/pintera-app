<?php
// app/Services/Finance/SkipAlertResolver.php

namespace App\Services\Finance;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;

class SkipAlertResolver
{
    /**
     * Read-only replica of AutoAllocationEngine::run()'s priority ordering and
     * allocation walk (zero-or-skip semantics — a tagihan receiving ANY partial
     * payment is not "skipped"), used ONLY to compute what the banner should
     * show. Never touches the wallet or any tagihan row, and does not call
     * AutoAllocationEngine itself.
     *
     * One deliberate divergence: AutoAllocationEngine::run() returns early when
     * balance <= 0, before computing $skippedTagihan at all, so a zero-balance
     * wallet with outstanding tagihan produces no notification from the engine.
     * This resolver has no such early return — a zero balance still surfaces the
     * dashboard banner (the highest-priority tagihan is treated as fully
     * skipped), which is the correct proactive-warning behavior for a parent
     * viewing their own dashboard, even though the backend notification system
     * would stay silent in the same scenario.
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
