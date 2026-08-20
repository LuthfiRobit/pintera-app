<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class SubmitBuktiTugasData
{
    public function __construct(
        public ?string $teks,
        public ?string $lampiranPath,
    ) {}
}
