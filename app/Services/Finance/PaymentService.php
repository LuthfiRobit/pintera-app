<?php

namespace App\Services\Finance;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\PaymentException;
use App\Models\BriQrisPayment;
use App\Models\BriVirtualAccount;
use App\Models\ManualPaymentRequest;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected PaymentAllocationService $allocationService
    ) {
    }

    /**
     * Create VA payment for a collection of tagihan.
     */
    public function createVaPayment(Siswa $siswa, Collection $tagihans): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'va_bri', 'menunggu_pembayaran');
            
            $vaResult = $this->gateway->createVirtualAccount($pembayaran, 'BILL_DIRECT');
            
            BriVirtualAccount::create([
                'pembayaran_id' => $pembayaran->id,
                'va_type' => 'BILL_DIRECT',
                'va_number' => $vaResult->vaNumber,
                'amount' => $vaResult->amount,
                'expired_at' => $vaResult->expiredAt,
                'status' => 'WAITING',
                'callback_payload' => $vaResult->payload,
            ]);

            return $pembayaran;
        });
    }

    /**
     * Create QRIS payment.
     */
    public function createQrisPayment(Siswa $siswa, Collection $tagihans): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'qris', 'menunggu_pembayaran');
            
            $qrisResult = $this->gateway->createQris($pembayaran, 'DIRECT');
            
            BriQrisPayment::create([
                'pembayaran_id' => $pembayaran->id,
                'qris_type' => 'DIRECT',
                'amount' => $qrisResult->amount,
                'qr_code' => $qrisResult->qrCodeData,
                'expired_at' => $qrisResult->expiredAt,
                'status' => 'WAITING',
                'callback_payload' => $qrisResult->payload,
            ]);

            return $pembayaran;
        });
    }

    /**
     * Get or create permanent VA for a student's wallet.
     */
    public function getOrCreatePermanentVa(Siswa $siswa): BriVirtualAccount
    {
        $wallet = $siswa->wallet;
        if (!$wallet) {
            throw new PaymentException('Siswa tidak memiliki wallet.');
        }

        $existingVa = BriVirtualAccount::where('wallet_id', $wallet->id)
            ->where('va_type', 'WALLET_PERMANENT')
            ->first();

        if ($existingVa) {
            return $existingVa;
        }

        // We create a dummy pembayaran to pass to the gateway, or gateway shouldn't need it?
        // Actually, gateway interface requires Pembayaran, but for permanent VA we might not have a bill yet.
        // Let's create an empty pending pembayaran for tracking permanent VA creation if required,
        // or just use a dummy one.
        $dummyPembayaran = Pembayaran::create([
            'siswa_id' => $siswa->id,
            'metode' => 'va_bri',
            'status' => 'menunggu_pembayaran',
            'channel_reference' => 'WALLET_PERMANENT',
        ]);

        $vaResult = $this->gateway->createVirtualAccount($dummyPembayaran, 'WALLET_PERMANENT');

        return BriVirtualAccount::create([
            'pembayaran_id' => $dummyPembayaran->id,
            'wallet_id' => $wallet->id,
            'va_type' => 'WALLET_PERMANENT',
            'va_number' => $vaResult->vaNumber,
            'amount' => null,
            'expired_at' => null,
            'status' => 'PERMANENT',
            'callback_payload' => $vaResult->payload,
        ]);
    }

    /**
     * Create manual payment request.
     */
    public function createManualPayment(Siswa $siswa, Collection $tagihans, array $data): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans, $data) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'transfer_manual', 'menunggu_verifikasi');

            ManualPaymentRequest::create([
                'pembayaran_id' => $pembayaran->id,
                'requested_by' => $data['requested_by'],
                'amount' => $data['amount'],
                'transfer_proof_path' => $data['transfer_proof_path'],
                'bank_origin' => $data['bank_origin'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'status' => 'PENDING',
            ]);

            return $pembayaran;
        });
    }

    /**
     * Create a manual transfer request intended as a wallet top-up (not tied to any tagihan).
     */
    public function createManualTopupPayment(Siswa $siswa, array $data): Pembayaran
    {
        return DB::transaction(function () use ($siswa, $data) {
            $pembayaran = Pembayaran::create([
                'siswa_id' => $siswa->id,
                'metode' => 'transfer_manual',
                'status' => 'menunggu_verifikasi',
                'amount' => $data['amount'],
                'topup_status' => 'pending',
                'channel_reference' => (string) Str::uuid(),
            ]);

            ManualPaymentRequest::create([
                'pembayaran_id' => $pembayaran->id,
                'requested_by' => $data['requested_by'],
                'amount' => $data['amount'],
                'transfer_proof_path' => $data['transfer_proof_path'],
                'bank_origin' => $data['bank_origin'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'status' => 'PENDING',
            ]);

            return $pembayaran;
        });
    }

    /**
     * Create instant cash payment (kasir).
     */
    public function createCashPayment(Siswa $siswa, Collection $tagihans, int $cashierUserId): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans, $cashierUserId) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'cash', 'lunas');
            
            $pembayaran->update([
                'diverifikasi_oleh_user_id' => $cashierUserId,
                'diverifikasi_pada' => now(),
            ]);

            // For cash, we allocate immediately
            $this->allocationService->allocate($pembayaran);

            return $pembayaran;
        });
    }

    protected function guardAgainstInvalidTagihan(Collection $tagihans): void
    {
        foreach ($tagihans as $tagihan) {
            if (in_array($tagihan->status, ['dibatalkan', 'lunas'])) {
                throw new PaymentException('Terdapat tagihan yang sudah dibatalkan atau lunas.');
            }
        }
    }

    protected function createPembayaranRecord(Siswa $siswa, Collection $tagihans, string $metode, string $status): Pembayaran
    {
        $pembayaran = Pembayaran::create([
            'siswa_id' => $siswa->id,
            'metode' => $metode,
            'status' => $status,
            'channel_reference' => (string) Str::uuid(),
        ]);

        foreach ($tagihans as $tagihan) {
            $amountToPay = $tagihan->net_amount - $tagihan->paid_amount;
            if ($amountToPay > 0) {
                PembayaranTagihan::create([
                    'pembayaran_id' => $pembayaran->id,
                    'tagihan_id' => $tagihan->id,
                    'amount_allocated' => $amountToPay,
                ]);
            }
        }

        return $pembayaran;
    }
}
