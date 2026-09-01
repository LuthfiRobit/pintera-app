<?php

namespace App\Domains\Keuangan\Listeners;

use App\Domains\Keuangan\Events\BillTypeActivated;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;

class GenerateTagihanForActivatedBillType
{
    public function __construct(private readonly TagihanBillingGenerator $generator) {}

    public function handle(BillTypeActivated $event): void
    {
        if ($event->jenisTagihan->kategori->isPpdb()) {
            return;
        }

        $this->generator->generate($event->jenisTagihan, 'event', 'BillTypeActivated');
    }
}
