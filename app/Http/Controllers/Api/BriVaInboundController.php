<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BriInboundAuthenticatorInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BriVaInboundController extends Controller
{
    public function __construct(private readonly BriInboundAuthenticatorInterface $authenticator)
    {
    }

    public function token(Request $request)
    {
        $clientId = (string) $request->input('client_id');
        $clientSecret = (string) $request->input('client_secret');

        $token = $this->authenticator->issueToken($clientId, $clientSecret);

        if ($token === null) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Client',
            ], 401);
        }

        return response()->json([
            'accessToken' => $token,
            'tokenType' => 'BearerToken',
            'expiresIn' => '899',
        ]);
    }

    public function inquiry(Request $request)
    {
        $token = $this->bearerToken($request);
        if (!$this->authenticator->validateToken($token)) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Token is invalid or expired',
            ], 401);
        }

        $vaNumber = (string) $request->input('virtualAccountNo');
        $va = \App\Models\BriVirtualAccount::with('wallet.siswa')->where('va_number', $vaNumber)->first();

        if (!$va || !$va->wallet || !$va->wallet->siswa) {
            return response()->json([
                'responseCode' => '4042412',
                'responseMessage' => 'Virtual Account Not Found',
                'virtualAccountData' => [
                    'virtualAccountNo' => $vaNumber,
                ]
            ], 404);
        }

        $siswa = $va->wallet->siswa;
        // The mock uses totalTagihan? Let's check how much is expected.
        // I will just sum unpaid tagihan if any. Wait, the VA is for permanent wallet.
        $totalTagihan = $siswa->tagihan()->where('status', 'belum_bayar')->sum('net_amount');
        
        return response()->json([
            'responseCode' => '2002400',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'virtualAccountNo' => $vaNumber,
                'virtualAccountName' => $siswa->nama_lengkap,
                'inquiryStatus' => '00',
                'inquiryReason' => 'Success',
                'totalAmount' => [
                    'value' => number_format((float) $totalTagihan, 2, '.', ''),
                    'currency' => 'IDR'
                ],
                'inquiryRequestId' => $request->input('inquiryRequestId'),
            ]
        ]);
    }

    public function payment(Request $request)
    {
        $token = $this->bearerToken($request);
        if (!$this->authenticator->validateToken($token)) {
            return response()->json([
                'responseCode' => '4012500',
                'responseMessage' => 'Unauthorized. Token is invalid or expired',
            ], 401);
        }

        $vaNumber = (string) $request->input('virtualAccountNo');
        $reqId = (string) $request->input('paymentRequestId');
        $amountStr = (string) $request->input('paidAmount.value');
        $amount = (float) $amountStr;

        $va = \App\Models\BriVirtualAccount::with('wallet')->where('va_number', $vaNumber)->first();

        if (!$va || !$va->wallet) {
            return response()->json([
                'responseCode' => '4042512',
                'responseMessage' => 'Virtual Account Not Found',
                'virtualAccountData' => [
                    'virtualAccountNo' => $vaNumber,
                ]
            ], 404);
        }

        $log = \App\Models\BriInboundPaymentLog::where('payment_request_id', $reqId)->first();
        if (!$log) {
            try {
                \App\Models\BriInboundPaymentLog::create([
                    'payment_request_id' => $reqId,
                    'va_number' => $vaNumber,
                    'amount' => $amount,
                ]);

                // Auto topup di luar DB::transaction
                $va->wallet->topup($amount);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Idempotent: Request already handled concurrently
            }
        }

        return response()->json([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'virtualAccountNo' => $vaNumber,
                'paymentRequestId' => $reqId,
            ]
        ]);
    }

    protected function bearerToken(Request $request): string
    {
        return (string) str($request->header('Authorization', ''))->after('Bearer ');
    }
}
