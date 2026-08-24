<?php

namespace App\Http\Controllers\Api\Keuangan;

use App\Domains\Keuangan\Actions\Webhook\InquiryBriVirtualAccountAction;
use App\Domains\Keuangan\Actions\Webhook\IssueBriAccessTokenAction;
use App\Domains\Keuangan\Actions\Webhook\ProcessBriVaPaymentAction;
use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BriVaInboundController extends Controller
{
    public function __construct(private readonly BriInboundAuthenticatorInterface $authenticator)
    {
    }

    public function token(Request $request, IssueBriAccessTokenAction $action)
    {
        $clientId = (string) $request->input('client_id');
        $clientSecret = (string) $request->input('client_secret');

        $token = $action->execute($clientId, $clientSecret);

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

    public function inquiry(Request $request, InquiryBriVirtualAccountAction $action)
    {
        if (!$this->authenticator->validateToken($this->bearerToken($request))) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Invalid Token (B2B)',
            ], 401);
        }

        $vaNumber = trim((string) $request->input('virtualAccountNo'));

        $result = $action->execute($vaNumber);

        if ($result === null) {
            return response()->json([
                'responseCode' => '4042412',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404);
        }

        return response()->json([
            'responseCode' => '2002400',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'virtualAccountName' => $result->virtualAccountName,
                'inquiryRequestId' => (string) $request->input('inquiryRequestId'),
                'totalAmount' => [
                    'value' => number_format($result->saranNominal, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'inquiryStatus' => '00',
            ],
        ]);
    }

    public function payment(Request $request, ProcessBriVaPaymentAction $action)
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

        $outcome = $action->execute($vaNumber, $paymentRequestId, $amount);

        return match ($outcome->status) {
            'invalid_amount' => response()->json([
                'responseCode' => '4042513',
                'responseMessage' => 'Invalid Amount',
            ], 404),
            'va_not_found' => response()->json([
                'responseCode' => '4042512',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404),
            'log_write_failed' => response()->json([
                'responseCode' => '5002500',
                'responseMessage' => 'Internal Server Error',
            ], 500),
            'success' => $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $outcome->amount, $outcome->virtualAccountName),
        };
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