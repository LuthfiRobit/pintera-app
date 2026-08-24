<?php

namespace App\Domains\Keuangan\Services\Gateway;

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;

class HybridPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected BriSnapGateway $bri,
        protected MockPaymentGateway $mock,
    ) {
    }

    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        return $this->mock->createVirtualAccount($pembayaran, $vaType);
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        return $this->bri->createQris($pembayaran, $qrisType);
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        return $this->mock->verifyCallbackSignature($payload, $signature);
    }

    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        return $type === 'qris'
            ? $this->bri->checkStatus($channelReference, $type)
            : $this->mock->checkStatus($channelReference, $type);
    }
}