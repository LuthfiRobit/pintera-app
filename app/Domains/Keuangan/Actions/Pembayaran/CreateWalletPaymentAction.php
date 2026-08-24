<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class CreateWalletPaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @throws InsufficientBalanceException|PaymentException
     */
    public function execute(Siswa $siswa, Collection $tagihans): Pembayaran
    {
        return $this->paymentService->createWalletPayment($siswa, $tagihans);
    }
}