<?php

namespace App\Domains\Keuangan\DataTransferObjects;

class PaymentStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly array $payload
    ) {
    }
}