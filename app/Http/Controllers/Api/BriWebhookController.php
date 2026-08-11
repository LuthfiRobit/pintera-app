<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\BriVirtualAccount;
use App\Models\Pembayaran;
use App\Models\Wallet;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BriWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected PaymentAllocationService $allocationService
    ) {
    }

    public function handlePaymentNotification(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('BRI-Signature');

        if (!$this->gateway->verifyCallbackSignature($payload, (string) $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();
        $brivaNo = $data['BrivaNo'] ?? '';
        $custCode = $data['CustCode'] ?? '';
        $vaNumber = $brivaNo . $custCode;
        $status = $data['Status'] ?? '';
        $amountPaid = floatval($data['Amount'] ?? 0);

        if ($status !== 'PAID') {
            return response()->json(['status' => 'success', 'message' => 'Ignored non-PAID status']);
        }

        try {
            DB::transaction(function () use ($vaNumber, $amountPaid, $data) {
                // Lock VA record to prevent race conditions
                $va = BriVirtualAccount::where('va_number', $vaNumber)->lockForUpdate()->first();

                if (!$va) {
                    throw new \Exception("VA Number {$vaNumber} not found");
                }

                if ($va->va_type === 'BILL_DIRECT') {
                    if ($va->status === 'PAID') {
                        // Idempotent: Already paid
                        return;
                    }

                    $va->status = 'PAID';
                    $va->save();

                    $pembayaran = Pembayaran::find($va->pembayaran_id);
                    if ($pembayaran && $pembayaran->status !== 'lunas') {
                        $pembayaran->status = 'lunas';
                        $pembayaran->save();

                        // Allocate payment to bills
                        $this->allocationService->allocate($pembayaran);
                    }
                } elseif ($va->va_type === 'WALLET_PERMANENT') {
                    // For permanent VA, we top up the wallet directly
                    $wallet = Wallet::lockForUpdate()->find($va->wallet_id);
                    if (!$wallet) {
                        throw new \Exception("Wallet not found for VA {$vaNumber}");
                    }

                    // Create topup history record
                    $pembayaran = Pembayaran::create([
                        'siswa_id' => $wallet->siswa_id,
                        'metode' => 'va_bri',
                        'status' => 'lunas', // Instant lunas
                        'topup_status' => 'pending', // Initial status
                        'channel_reference' => 'WALLET_PERMANENT_TOPUP',
                    ]);

                    try {
                        $wallet->topup($amountPaid, $pembayaran, 'Topup via Permanent VA');
                        $pembayaran->update(['topup_status' => 'completed']);
                    } catch (\Exception $e) {
                        Log::error("Failed to topup wallet: " . $e->getMessage());
                        $pembayaran->update(['topup_status' => 'failed']);
                        // Re-throw if you want it to retry, or swallow it so we return 200 to gateway
                        // Requirements: "if topup() fails, log error, return 200, status=failed"
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error("Webhook error: " . $e->getMessage());
            // Depending on gateway spec, you might return 500 to force retry, 
            // but for now we log and return 200 if we handled it gracefully.
            // If it's a critical DB lock issue, let it bubble up or return 500.
        }

        return response()->json(['status' => 'success']);
    }
}
