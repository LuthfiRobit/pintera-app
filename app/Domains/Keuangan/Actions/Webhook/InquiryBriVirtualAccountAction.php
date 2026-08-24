<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Webhook;

use App\Domains\Keuangan\DataTransferObjects\BriVaInquiryResult;
use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;

class InquiryBriVirtualAccountAction
{
    public function execute(string $vaNumber): ?BriVaInquiryResult
    {
        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        if (!$va || !$va->wallet || !$va->wallet->siswa) {
            return null;
        }

        $siswa = $va->wallet->siswa;

        $tagihanJatuhTempo = Tagihan::where('tagihable_type', Siswa::class)
            ->where('tagihable_id', $siswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->orderBy('jatuh_tempo')
            ->first();

        $saranNominal = $tagihanJatuhTempo
            ? (float) $tagihanJatuhTempo->net_amount - (float) $tagihanJatuhTempo->paid_amount
            : 0.0;

        return new BriVaInquiryResult($siswa->nama_lengkap, $saranNominal);
    }
}