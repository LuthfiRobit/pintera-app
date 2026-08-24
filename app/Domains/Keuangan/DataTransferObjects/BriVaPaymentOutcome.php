<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class BriVaPaymentOutcome
{
    private function __construct(
        public string $status,
        public ?float $amount = null,
        public ?string $virtualAccountName = null,
    ) {}

    public static function invalidAmount(): self
    {
        return new self('invalid_amount');
    }

    public static function vaNotFound(): self
    {
        return new self('va_not_found');
    }

    public static function logWriteFailed(): self
    {
        return new self('log_write_failed');
    }

    public static function success(float $amount, ?string $virtualAccountName): self
    {
        return new self('success', $amount, $virtualAccountName);
    }
}