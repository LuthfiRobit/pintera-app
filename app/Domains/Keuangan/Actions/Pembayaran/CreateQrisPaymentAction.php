<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class CreateQrisPaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @throws PaymentException
     */
    public function execute(Siswa $siswa, Collection $tagihans, float $topupAmount): Pembayaran
    {
        return $topupAmount > 0
            ? $this->paymentService->createQrisPaymentWithTopup($siswa, $tagihans, $topupAmount)
            : $this->paymentService->createQrisPayment($siswa, $tagihans);
    }
}