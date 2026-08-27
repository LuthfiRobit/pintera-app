<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KurikulumAssignmentData
{
    public function __construct(
        public string $bentukPendidikan,
        public ?string $tingkat,
        public string $kurikulum,
        public ?int $lembagaId,
        public int $tahunAjaranId,
    ) {}
}
