<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

use App\Domains\Akademik\Enums\AssessmentType;

final readonly class RekapNilaiSel
{
    public function __construct(
        public AssessmentType $assessmentType,
        public string $label,
        public ?bool $tuntas,
    ) {}
}
