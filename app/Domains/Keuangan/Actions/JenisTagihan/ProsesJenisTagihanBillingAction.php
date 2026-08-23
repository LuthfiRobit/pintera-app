<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\BillingJobLog;
use App\Services\TagihanBillingGenerator;

class ProsesJenisTagihanBillingAction
{
    public function __construct(
        private readonly TagihanBillingGenerator $generator,
    ) {}

    public function execute(JenisTagihan $jenisTagihan): BillingJobLog
    {
        return $this->generator->generate($jenisTagihan, 'manual');
    }
}
