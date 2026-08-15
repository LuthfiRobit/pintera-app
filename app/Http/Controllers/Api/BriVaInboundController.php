<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BriInboundAuthenticatorInterface;
use App\Http\Controllers\Controller;
use App\Models\BriInboundPaymentLog;
use App\Models\BriVirtualAccount;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        if (!$this->authenticator->validateToken($this->bearerToken($request))) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Invalid Token (B2B)',
            ], 401);
        }

        $vaNumber = trim((string) $request->input('virtualAccountNo'));

        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        if (!$va || !$va->wallet || !$va->wallet->siswa) {
            return response()->json([
                'responseCode' => '4042412',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404);
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

        return response()->json([
            'responseCode' => '2002400',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'virtualAccountName' => $siswa->nama_lengkap,
                'inquiryRequestId' => (string) $request->input('inquiryRequestId'),
                'totalAmount' => [
                    'value' => number_format($saranNominal, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'inquiryStatus' => '00',
            ],
        ]);
    }

    public function payment(Request $request)
    {
        if (!$this->authenticator->validateToken($this->bearerToken($request))) {
            return response()->json([
                'responseCode' => '4012500',
                'responseMessage' => 'Unauthorized. Invalid Token (B2B)',
            ], 401);
        }

        $vaNumber = trim((string) $request->input('virtualAccountNo'));
        $paymentRequestId = (string) $request->input('paymentRequestId');
        $amount = (float) data_get($request->input('paidAmount'), 'value', 0);

        if ($vaNumber === '' || $paymentRequestId === '') {
            return response()->json([
                'responseCode' => '4002500',
                'responseMessage' => 'Invalid Mandatory Field',
            ], 400);
        }

        $existingLog = BriInboundPaymentLog::where('payment_request_id', $paymentRequestId)->first();
        if ($existingLog) {
            return $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $existingLog->amount);
        }

        if ($amount <= 0) {
            return response()->json([
                'responseCode' => '4042513',
                'responseMessage' => 'Invalid Amount',
            ], 404);
        }

        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet')->first();

        if (!$va || !$va->wallet) {
            return response()->json([
                'responseCode' => '4042512',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404);
        }

        $wallet = $va->wallet;

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
            // Race: another request already logged this paymentRequestId first --
            // safe to treat as a duplicate report (idempotent replay).
            $pembayaran->delete();

            return $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $amount);
        }

        try {
            $wallet->topup($amount, $pembayaran, 'Top-up via VA BRI');
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (\Throwable $e) {
            Log::error("Gagal proses auto-debit setelah topup VA {$vaNumber}: " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);
            $pembayaran->update(['topup_status' => 'failed']);
        }

        return $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $amount);
    }

    protected function paymentSuccessResponse(string $vaNumber, string $paymentRequestId, float $amount)
    {
        return response()->json([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'paymentRequestId' => $paymentRequestId,
                'paidAmount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'paymentFlagStatus' => '00',
            ],
        ]);
    }

    protected function bearerToken(Request $request): string
    {
        return (string) str($request->header('Authorization', ''))->after('Bearer ');
    }
}
