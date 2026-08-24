<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\VirtualAccount;

use App\Domains\Keuangan\Services\PaymentService;
use App\Models\Siswa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class GenerateVirtualAccountAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @param  Collection<int, Siswa>  $siswaList
     * @return array{berhasil: int, gagalNama: array<int, string>}
     */
    public function execute(Collection $siswaList): array
    {
        $berhasil = 0;
        $gagalNama = [];

        foreach ($siswaList as $siswa) {
            try {
                $this->paymentService->getOrCreatePermanentVa($siswa);
                $berhasil++;
            } catch (\Throwable $e) {
                Log::error("Gagal generate VA untuk siswa id={$siswa->id}: ".$e->getMessage());
                $gagalNama[] = $siswa->nama_lengkap;
            }
        }

        return ['berhasil' => $berhasil, 'gagalNama' => $gagalNama];
    }
}