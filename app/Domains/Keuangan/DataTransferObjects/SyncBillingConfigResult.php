<?php

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class SyncBillingConfigResult
{
    public function __construct(
        public bool $tarifBerubah,
        public bool $keringananBerubah,
    ) {}
}
