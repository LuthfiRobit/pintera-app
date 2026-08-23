<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Models\Cicilan;
use App\Services\PembayaranService;

class CatatManualCicilanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Cicilan $cicilan, string $dicatatOleh, int $userId): void
    {
        $this->service->catatPembayaran(null, $cicilan, $dicatatOleh, null, $userId);
    }
}