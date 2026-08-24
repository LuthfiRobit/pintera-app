<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class BriVaInquiryResult
{
    public function __construct(
        public string $virtualAccountName,
        public float $saranNominal,
    ) {}
}