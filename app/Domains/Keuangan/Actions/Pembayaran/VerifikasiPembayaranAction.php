<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PembayaranService;

class VerifikasiPembayaranAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Pembayaran $pembayaran, string $keputusan, ?string $catatan, int $adminUserId): void
    {
        $this->service->verifikasiPembayaran($pembayaran, $keputusan, $catatan, $adminUserId);
    }
}