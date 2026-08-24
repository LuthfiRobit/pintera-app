<?php

namespace App\Domains\Keuangan\DataTransferObjects;

class VirtualAccountResult
{
    public function __construct(
        public readonly string $vaNumber,
        public readonly ?float $amount,
        public readonly ?\DateTimeInterface $expiredAt,
        public readonly array $payload
    ) {
    }
}