<?php

namespace App\Domains\Keuangan\Services\Gateway;

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        $vaNumber = 'MOCK-VA-' . str_pad($pembayaran->id, 6, '0', STR_PAD_LEFT);
        $amount = $vaType === 'WALLET_PERMANENT' ? null : 10000;
        $expiredAt = $vaType === 'WALLET_PERMANENT' ? null : now()->addHours(24);

        return new VirtualAccountResult(
            $vaNumber,
            $amount,
            $expiredAt,
            ['mock_response' => true]
        );
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        $qrCodeData = 'MOCK-QR-' . str_pad($pembayaran->id, 6, '0', STR_PAD_LEFT);
        $amount = 10000;

        return new QrisResult(
            $qrCodeData,
            $amount,
            now()->addMinutes(15),
            ['mock_response' => true]
        );
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        return true;
    }

    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        return new PaymentStatusResult('PAID', ['mock_response' => true]);
    }
}