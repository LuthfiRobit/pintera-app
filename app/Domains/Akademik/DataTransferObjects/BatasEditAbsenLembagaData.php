<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class BatasEditAbsenLembagaData
{
    public function __construct(
        public int $batasHari,
    ) {}
}
