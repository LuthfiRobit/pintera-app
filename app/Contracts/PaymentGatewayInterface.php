<?php

namespace App\Contracts;

use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\DTO\VirtualAccountResult;
use App\Models\Pembayaran;

interface PaymentGatewayInterface
{
    /**
     * Create a virtual account.
     */
    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult;

    /**
     * Create a QRIS payment.
     */
    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult;

    /**
     * Verify callback signature.
     */
    public function verifyCallbackSignature(string $payload, string $signature): bool;

    /**
     * Check payment status by channel reference (VA number or QRIS ID).
     */
    public function checkStatus(string $channelReference): PaymentStatusResult;
}
