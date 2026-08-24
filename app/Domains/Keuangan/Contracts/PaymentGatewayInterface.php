<?php

namespace App\Domains\Keuangan\Contracts;

use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;

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
     * Check payment status by channel reference (VA number or QRIS reference).
     */
    public function checkStatus(string $channelReference, string $type): PaymentStatusResult;
}