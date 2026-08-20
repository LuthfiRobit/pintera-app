<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class UpdateStatusSesiData
{
    public function __construct(
        public string $status,
        public ?string $catatanInternal,
        public ?string $alasanBatal,
    ) {}
}
