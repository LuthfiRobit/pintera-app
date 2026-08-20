<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class PolaJamData
{
    public function __construct(
        public string $nama,
        public ?int $lembagaId = null,
    ) {}
}
