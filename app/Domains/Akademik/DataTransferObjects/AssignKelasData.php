<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class AssignKelasData
{
    /**
     * @param  array<int, int>  $kelasIds
     */
    public function __construct(
        public array $kelasIds,
    ) {}
}
