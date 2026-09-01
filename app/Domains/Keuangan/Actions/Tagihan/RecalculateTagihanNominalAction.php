<?php

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Domains\Keuangan\Services\TagihanStatusResolver;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Notifications\Finance\TagihanDirevisiNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateTagihanNominalAction
{
    public function __construct(
        private readonly TagihanNominalResolver $nominalResolver,
        private readonly TagihanStatusResolver $statusResolver,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function execute(int $tagihanId): void
    {
        DB::transaction(function () use ($tagihanId) {
            $tagihan = Tagihan::withoutGlobalScope(TenantScope::class)->lockForUpdate()->find($tagihanId);

            if ($tagihan === null || in_array($tagihan->status, ['lunas', 'dibatalkan'], true)) {
                return;
            }

            // Guard defensif WAJIB, terlepas dari bagaimana query pemanggil ditulis: Sasaran/
            // Tarif/Keringanan cuma berlaku untuk tagihan Siswa. Tagihan PPDB (tagihable_type =
            // Pendaftaran::class) pakai mekanisme nominal-per-jalur yang berbeda total.
            if ($tagihan->tagihable_type !== Siswa::class) {
                return;
            }

            $siswa = Siswa::withoutGlobalScope(TenantScope::class)->find($tagihan->tagihable_id);
            $jenisTagihan = $tagihan->jenisTagihan;

            if ($siswa === null || $jenisTagihan === null) {
                return;
            }

            $netAmountLama = (float) $tagihan->net_amount;

            $resolved = $this->nominalResolver->resolve($siswa, $jenisTagihan);
            $newNetAmount = max(0, $resolved['nominal'] - $resolved['discount_amount']);

            $adaOverpayment = $newNetAmount < (float) $tagihan->paid_amount;
            $adaCicilan = $tagihan->skemaCicilan()->exists();

            if ($adaOverpayment || $adaCicilan) {
                $alasan = $adaOverpayment
                    ? 'Net amount baru Rp'.number_format($newNetAmount, 0, ',', '.').' lebih kecil dari yang sudah dibayar Rp'.number_format((float) $tagihan->paid_amount, 0, ',', '.')
                    : 'Tagihan sudah punya skema cicilan -- rekonsiliasi manual via halaman cicilan.';

                $tagihan->update(['perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => $alasan]);

                return;
            }

            $tagihan->total_tagihan = $resolved['nominal'];
            $tagihan->discount_amount = $resolved['discount_amount'];
            $tagihan->discount_type = $resolved['discount_type'];
            $tagihan->net_amount = $newNetAmount;
            $tagihan->status = $this->statusResolver->resolve((float) $tagihan->paid_amount, $newNetAmount, $tagihan->status);
            $tagihan->perlu_ditinjau_ulang = false;
            $tagihan->alasan_perlu_ditinjau = null;
            $tagihan->save();

            if ($netAmountLama !== $newNetAmount) {
                $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
                if ($kontakUtama !== null) {
                    try {
                        $this->dispatcher->send($kontakUtama, new TagihanDirevisiNotification($tagihan->fresh(), $netAmountLama));
                    } catch (\Throwable $e) {
                        Log::error('Gagal mengirim TagihanDirevisiNotification: '.$e->getMessage());
                    }
                }
            }
        });
    }
}
