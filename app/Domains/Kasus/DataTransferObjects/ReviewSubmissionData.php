<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class ReviewSubmissionData
{
    public function __construct(
        public string $statusReview,
        public ?string $catatanRevisi,
    ) {}
}
