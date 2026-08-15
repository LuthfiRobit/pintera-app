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
        $partnerServiceId = str_pad((string) config('services.bri.inbound.partner_service_id'), 8, '0', STR_PAD_LEFT);
        $customerNo = str_pad((string) $pembayaran->siswa_id, 20, '0', STR_PAD_LEFT);
        $vaNumber = $partnerServiceId . $customerNo;

        return new VirtualAccountResult($vaNumber, null, null, [
            'partnerServiceId' => $partnerServiceId,
            'customerNo' => $customerNo,
        ]);
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        $payload = [
            // channel_reference is a 36-char UUID and BRI's docs contradict themselves on
            // partnerReferenceNo's allowed length, so we use the numeric pembayaran id
            // (zero-padded) to avoid that ambiguity entirely.
            'partnerReferenceNo' => str_pad((string) $pembayaran->id, 6, '0', STR_PAD_LEFT),
            'amount' => [
                'value' => number_format($pembayaran->amount, 2, '.', ''),
                'currency' => 'IDR'
            ],
            'merchantId' => config('services.bri.merchant_id'),
            'terminalId' => config('services.bri.terminal_id'),
        ];

        $response = $this->client->post('/snap/v1.1/qr/qr-mpm-generate', $payload);

        if (!isset($response['qrContent'])) {
            throw new \App\Exceptions\BriApiException(
                (string) ($response['responseCode'] ?? 'unknown'),
                'BRI response missing qrContent field'
            );
        }

        return new QrisResult(
            $response['qrContent'],
            $pembayaran->amount,
            now()->addMinutes(15),
            $response
        );
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        throw new \RuntimeException('BriSnapGateway VA not fully implemented yet');
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

            // Mapped to WAITING/PAID/FAILED to match the bri_qris_payments.status enum
            // values, intentionally not the plan's raw PENDING/PAID binary.
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
