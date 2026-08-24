<?php

namespace App\Http\Controllers\Api;

use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;
use App\Exceptions\AutoAllocationFailedException;
use App\Http\Controllers\Controller;
use App\Models\BriInboundPaymentLog;
use App\Models\BriVirtualAccount;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
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
            return $this->paymentSuccessResponse(
                $vaNumber,
                $paymentRequestId,
                $existingLog->amount,
                $this->resolveVirtualAccountName($vaNumber)
            );
        }

        if ($amount <= 0) {
            return response()->json([
                'responseCode' => '4042513',
                'responseMessage' => 'Invalid Amount',
            ], 404);
        }

        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        if (!$va || !$va->wallet) {
            return response()->json([
                'responseCode' => '4042512',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404);
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
                return $this->paymentSuccessResponse(
                    $vaNumber,
                    $paymentRequestId,
                    $amount,
                    $virtualAccountName
                );
            }

            Log::error("Gagal menulis BriInboundPaymentLog (bukan duplikat) untuk VA {$vaNumber}: " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'responseCode' => '5002500',
                'responseMessage' => 'Internal Server Error',
            ], 500);
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

        return $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $amount, $virtualAccountName);
    }

    protected function resolveVirtualAccountName(string $vaNumber): ?string
    {
        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        return $va?->wallet?->siswa?->nama_lengkap;
    }

    protected function paymentSuccessResponse(string $vaNumber, string $paymentRequestId, float $amount, ?string $virtualAccountName = null)
    {
        return response()->json([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'virtualAccountName' => $virtualAccountName,
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
