<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\BriQrisPayment;
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
        $qrisReference = $data['QrCode'] ?? null;

        if ($status !== 'PAID') {
            return response()->json(['status' => 'success', 'message' => 'Ignored non-PAID status']);
        }

        $walletTopupData = null; // Store data to execute topup outside transaction

        try {
            DB::transaction(function () use ($vaNumber, $qrisReference, $amountPaid, &$walletTopupData) {
                if (!empty($vaNumber)) {
                    $va = BriVirtualAccount::where('va_number', $vaNumber)->lockForUpdate()->first();
                    if ($va) {
                        if ($va->status === 'PAID') {
                            return; // Idempotent
                        }
                        
                        if ($va->va_type === 'BILL_DIRECT') {
                            if ($va->amount !== null && bccomp((string) $va->amount, (string) $amountPaid, 2) !== 0) {
                                throw new \Exception("Amount mismatch for VA {$vaNumber}: expected {$va->amount}, got {$amountPaid}");
                            }

                            $va->status = 'PAID';
                            $va->save();

                            $pembayaran = Pembayaran::find($va->pembayaran_id);
                            if ($pembayaran && $pembayaran->status !== 'lunas') {
                                $pembayaran->status = 'lunas';
                                $pembayaran->save();
                                $this->allocationService->allocate($pembayaran);
                            }
                        } elseif ($va->va_type === 'WALLET_PERMANENT') {
                            $wallet = Wallet::lockForUpdate()->find($va->wallet_id);
                            if (!$wallet) {
                                throw new \Exception("Wallet not found for VA {$vaNumber}");
                            }

                            $pembayaran = Pembayaran::create([
                                'siswa_id' => $wallet->siswa_id,
                                'metode' => 'va_bri',
                                'amount' => $amountPaid,
                                'status' => 'lunas',
                                'topup_status' => 'pending',
                                'channel_reference' => 'WALLET_PERMANENT_TOPUP',
                            ]);

                            $walletTopupData = [
                                'wallet' => $wallet,
                                'pembayaran' => $pembayaran,
                                'amount' => $amountPaid
                            ];
                        }
                        return;
                    }
                }

                if (!empty($qrisReference)) {
                    $qris = BriQrisPayment::where('qr_code', $qrisReference)->lockForUpdate()->first();
                    if ($qris) {
                        if ($qris->status === 'PAID') {
                            return; // Idempotent
                        }

                        $qris->status = 'PAID';
                        $qris->save();

                        $pembayaran = Pembayaran::find($qris->pembayaran_id);
                        if ($pembayaran && $pembayaran->status !== 'lunas') {
                            $pembayaran->status = 'lunas';
                            $pembayaran->save();
                            $this->allocationService->allocate($pembayaran);
                        }
                        return;
                    }
                }

                throw new \Exception("Payment reference not found");
            });

            // Execute Wallet topup outside transaction
            if ($walletTopupData) {
                try {
                    $walletTopupData['wallet']->topup(
                        $walletTopupData['amount'], 
                        $walletTopupData['pembayaran'], 
                        'Topup via Permanent VA'
                    );
                    $walletTopupData['pembayaran']->update(['topup_status' => 'completed']);
                } catch (\Exception $e) {
                    Log::error("Failed to topup wallet: " . $e->getMessage());
                    $walletTopupData['pembayaran']->update(['topup_status' => 'failed']);
                }
            }

        } catch (\Exception $e) {
            Log::error("Webhook error: " . $e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Failed to process payment notification'], 500);
        }

        return response()->json(['status' => 'success']);
    }
}
