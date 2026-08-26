<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class FaseDefaultMappingData
{
    public function __construct(
        public string $bentukPendidikan,
        public ?string $tingkat,
        public int $faseId,
        public ?int $lembagaId,
    ) {}
}
