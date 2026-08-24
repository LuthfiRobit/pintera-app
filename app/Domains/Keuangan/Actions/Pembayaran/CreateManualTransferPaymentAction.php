<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class CreateManualTransferPaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @param  array{amount: float, transfer_proof_path: string, bank_origin: ?string, transfer_date: string, requested_by: int}  $data
     *
     * @throws PaymentException
     */
    public function execute(Siswa $siswa, Collection $tagihans, array $data): Pembayaran
    {
        return $this->paymentService->createManualPayment($siswa, $tagihans, $data);
    }
}