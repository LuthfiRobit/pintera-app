<?php

namespace App\Domains\Keuangan\Listeners;

use App\Domains\Keuangan\Events\BillTypeActivated;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;

class GenerateTagihanForActivatedBillType
{
    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    public function __construct(private readonly TagihanBillingGenerator $generator)
    {
    }

    public function handle(BillTypeActivated $event): void
    {
        if (in_array($event->jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return;
        }

        $this->generator->generate($event->jenisTagihan, 'event', 'BillTypeActivated');
    }
}