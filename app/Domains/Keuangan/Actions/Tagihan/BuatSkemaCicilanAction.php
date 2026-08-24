<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PembayaranService;

class BuatSkemaCicilanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Tagihan $tagihan, int $jumlahTermin, string $dibuatOleh, ?int $userId): void
    {
        $this->service->buatSkemaCicilan($tagihan, $jumlahTermin, $dibuatOleh, $userId);
    }
}