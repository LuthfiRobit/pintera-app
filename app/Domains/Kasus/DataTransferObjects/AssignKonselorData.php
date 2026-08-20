<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class AssignKonselorData
{
    public function __construct(
        public string $tingkatUrgensi,
        public string $konselorTipe,
        public int $konselorId,
    ) {}
}
