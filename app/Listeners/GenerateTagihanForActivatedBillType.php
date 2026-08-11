<?php

namespace App\Listeners;

use App\Events\BillTypeActivated;
use App\Services\TagihanBillingGenerator;

class GenerateTagihanForActivatedBillType
{
    public function __construct(private readonly TagihanBillingGenerator $generator)
    {
    }

    public function handle(BillTypeActivated $event): void
    {
        $this->generator->generate($event->jenisTagihan, 'event', 'BillTypeActivated');
    }
}
