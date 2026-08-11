<?php

namespace App\Services\Finance\Gateway;

use App\Contracts\PaymentGatewayInterface;
use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\DTO\VirtualAccountResult;
use App\Models\Pembayaran;

class BriSnapGateway implements PaymentGatewayInterface
{
    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
    }

    public function checkStatus(string $channelReference): PaymentStatusResult
    {
        throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
    }
}
