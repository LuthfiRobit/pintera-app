<?php

namespace App\Domains\Keuangan\Actions;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Services\TagihanBillingGenerator;

class ProsesJenisTagihanBillingAction
{
    public function __construct(
        private readonly TagihanBillingGenerator $generator,
    ) {}

    /**
     * @return array{created: int, skipped: int, failed: int}
     */
    public function execute(JenisTagihan $jenisTagihan): array
    {
        return $this->generator->generate($jenisTagihan, 'manual');
    }
}
