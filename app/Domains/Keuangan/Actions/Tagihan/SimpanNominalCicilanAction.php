<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Services\PembayaranService;

class SimpanNominalCicilanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @param  array<int, int>  $nominalPerTermin
     *
     * @throws \InvalidArgumentException
     */
    public function execute(SkemaCicilan $skemaCicilan, array $nominalPerTermin): void
    {
        $this->service->simpanNominalManual($skemaCicilan, $nominalPerTermin);
    }
}