<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PembayaranService;

class CatatManualTagihanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Tagihan $tagihan, string $dicatatOleh, int $userId): void
    {
        $this->service->catatPembayaran($tagihan, null, $dicatatOleh, null, $userId);
    }
}