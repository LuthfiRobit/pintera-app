<?php

namespace App\Services\Finance\Gateway;

use App\Contracts\PaymentGatewayInterface;
use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\DTO\VirtualAccountResult;
use App\Models\Pembayaran;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;

class BriSnapGateway implements PaymentGatewayInterface
{
    public function __construct(protected BriSnapClient $client)
    {
    }

    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        throw new \RuntimeException('BriSnapGateway VA not fully implemented yet');
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        $payload = [
            'partnerReferenceNo' => str_pad((string) $pembayaran->id, 6, '0', STR_PAD_LEFT),
            'amount' => [
                'value' => number_format($pembayaran->amount, 2, '.', ''),
                'currency' => 'IDR'
            ],
            'merchantId' => config('services.bri.merchant_id'),
            'terminalId' => config('services.bri.terminal_id'),
        ];

        $response = $this->client->post('/snap/v1.1/qr/qr-mpm-generate', $payload);

        return new QrisResult(
            $response['qrContent'],
            $pembayaran->amount,
            now()->addMinutes(15),
            ['referenceNo' => $response['referenceNo'] ?? null]
        );
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        return false;
    }

    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        if ($type === 'qris') {
            $payload = [
                'originalReferenceNo' => $channelReference,
                'serviceCode' => '47',
                'additionalInfo' => [
                    'terminalId' => config('services.bri.terminal_id'),
                ]
            ];

            $response = $this->client->post('/snap/v1.1/qr/qr-mpm-query', $payload);

            $status = 'WAITING';
            if (($response['latestTransactionStatus'] ?? '') === '00') {
                $status = 'PAID';
            } elseif (in_array(($response['latestTransactionStatus'] ?? ''), ['04', '05', '06'])) {
                $status = 'FAILED';
            }

            return new PaymentStatusResult($status, $response);
        }

        throw new \RuntimeException('BriSnapGateway VA checkStatus not fully implemented yet');
    }
}
