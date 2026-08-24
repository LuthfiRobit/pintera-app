<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Notifications\Finance\TransferManualDitolakNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RejectManualPaymentAction
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function execute(ManualPaymentRequest $manualPaymentRequest, int $reviewedByUserId, string $rejectionReason): void
    {
        if ($manualPaymentRequest->status !== 'PENDING') {
            abort(422, 'Permintaan ini sudah diproses sebelumnya.');
        }

        DB::transaction(function () use ($manualPaymentRequest, $reviewedByUserId, $rejectionReason) {
            $manualPaymentRequest->update([
                'status' => 'REJECTED',
                'reviewed_by' => $reviewedByUserId,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            // Reject tidak pernah memicu Wallet::topup() — baik kasus bill maupun topup,
            // ditolak berarti tidak ada dana yang masuk sama sekali, cukup ubah status.
            $manualPaymentRequest->pembayaran->update(['status' => 'ditolak']);
        });

        $siswa = $manualPaymentRequest->pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                $this->dispatcher->send($kontakUtama, new TransferManualDitolakNotification($rejectionReason));
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDitolakNotification: '.$e->getMessage());
            }
        }
    }
}