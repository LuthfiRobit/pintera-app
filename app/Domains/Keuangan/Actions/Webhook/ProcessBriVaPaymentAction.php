<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Webhook;

use App\Domains\Keuangan\DataTransferObjects\BriVaPaymentOutcome;
use App\Domains\Keuangan\Models\BriInboundPaymentLog;
use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Exceptions\AutoAllocationFailedException;
use Illuminate\Support\Facades\Log;

class ProcessBriVaPaymentAction
{
    public function execute(string $vaNumber, string $paymentRequestId, float $amount): BriVaPaymentOutcome
    {
        $existingLog = BriInboundPaymentLog::where('payment_request_id', $paymentRequestId)->first();
        if ($existingLog) {
            return BriVaPaymentOutcome::success(
                (float) $existingLog->amount,
                $this->resolveVirtualAccountName($vaNumber)
            );
        }

        if ($amount <= 0) {
            return BriVaPaymentOutcome::invalidAmount();
        }

        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        if (!$va || !$va->wallet) {
            return BriVaPaymentOutcome::vaNotFound();
        }

        $wallet = $va->wallet;
        $virtualAccountName = $wallet->siswa?->nama_lengkap;

        $pembayaran = Pembayaran::create([
            'siswa_id' => $wallet->siswa_id,
            'wallet_id' => $wallet->id,
            'metode' => 'va_bri',
            'amount' => $amount,
            'status' => 'lunas',
            'topup_status' => 'pending',
            'channel_reference' => $paymentRequestId,
        ]);

        try {
            BriInboundPaymentLog::create([
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'pembayaran_id' => $pembayaran->id,
            ]);
        } catch (\Throwable $e) {
            // Only treat this as a safe idempotent replay if a log row for this
            // paymentRequestId genuinely exists now (a concurrent duplicate request
            // won the race and inserted it first). If it does NOT exist, the insert
            // failed for some other reason (connection issue, unrelated constraint
            // violation, etc.) -- that is a real, unrecovered failure: the Pembayaran
            // we just created has no ledger backing and no wallet credit, so delete
            // the orphan, log it for investigation, and tell BRI to retry.
            $isGenuineDuplicate = BriInboundPaymentLog::where('payment_request_id', $paymentRequestId)->exists();

            $pembayaran->delete();

            if ($isGenuineDuplicate) {
                return BriVaPaymentOutcome::success($amount, $virtualAccountName);
            }

            Log::error("Gagal menulis BriInboundPaymentLog (bukan duplikat) untuk VA {$vaNumber}: " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);

            return BriVaPaymentOutcome::logWriteFailed();
        }

        try {
            $wallet->topup($amount, $pembayaran, 'Top-up via VA BRI');
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (AutoAllocationFailedException $e) {
            // The wallet balance itself WAS already credited successfully (that
            // increment committed inside Wallet::topup()'s own DB transaction,
            // before AutoAllocationEngine::run() ever ran) -- only the subsequent
            // auto-allocation step failed. topup_status must reflect that the
            // credit is done, otherwise ReconcilePayments::retryFailedTopups()
            // would re-select this Pembayaran and double-credit the wallet.
            Log::error("Auto-alokasi gagal setelah topup VA {$vaNumber} berhasil di-kredit (saldo AMAN, hanya alokasi yang gagal): " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (\Throwable $e) {
            // Genuine topup failure -- the balance was NOT credited (the internal
            // transaction rolled back), so this really does need a retry.
            Log::error("Gagal proses topup VA {$vaNumber}: " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);
            $pembayaran->update(['topup_status' => 'failed']);
        }

        return BriVaPaymentOutcome::success($amount, $virtualAccountName);
    }

    protected function resolveVirtualAccountName(string $vaNumber): ?string
    {
        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        return $va?->wallet?->siswa?->nama_lengkap;
    }
}