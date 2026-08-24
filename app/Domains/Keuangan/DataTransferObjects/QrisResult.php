<?php

namespace App\Domains\Keuangan\DataTransferObjects;

class QrisResult
{
    public function __construct(
        public readonly string $qrCodeData,
        public readonly float $amount,
        public readonly \DateTimeInterface $expiredAt,
        public readonly array $payload
    ) {
    }
}