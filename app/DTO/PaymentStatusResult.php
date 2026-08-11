<?php

namespace App\DTO;

class PaymentStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly array $payload
    ) {
    }
}
