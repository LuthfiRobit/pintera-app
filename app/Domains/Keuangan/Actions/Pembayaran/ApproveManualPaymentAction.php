<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Exceptions\AutoAllocationFailedException;
use App\Domains\Keuangan\Models\Wallet;
use App\Notifications\Finance\TransferManualDisetujuiNotification;
use App\Services\Finance\NotificationDispatcher;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApproveManualPaymentAction
{
    public function __construct(
        private readonly PaymentAllocationService $allocationService,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(ManualPaymentRequest $manualPaymentRequest, int $reviewedByUserId): void
    {
        if ($manualPaymentRequest->status !== 'PENDING') {
            abort(422, 'Permintaan ini sudah diproses sebelumnya.');
        }

        $pembayaran = $manualPaymentRequest->pembayaran;

        // Cross-validasi diskriminator SEBELUM dipercaya — topup_status dan keberadaan
        // pembayaran_tagihan wajib konsisten (mutually exclusive by construction, karena
        // createManualPayment() dan createManualTopupPayment() adalah 2 jalur creation
        // terpisah), tapi endpoint ini TIDAK BOLEH cuma percaya topup_status mentah-mentah —
        // kalau suatu saat data drift terjadi, approve() bisa diam-diam salah: skip topup
        // yang seharusnya jalan, ATAU skip alokasi tagihan sambil tetap menandai lunas.
        // Uang nyata terlibat — lebih baik gagal keras & jelas daripada salah diam-diam.
        $hasTagihanTargets = $pembayaran->pembayaranTagihan()->exists();
        $isTopup = $pembayaran->topup_status !== 'none';

        if ($hasTagihanTargets && $isTopup) {
            Log::critical("Manual payment guard mismatch: pembayaran id={$pembayaran->id} punya target tagihan (hasTagihanTargets=true) sekaligus ditandai topup (isTopup=true).");
            abort(500, 'Data pembayaran tidak konsisten: punya target tagihan sekaligus ditandai topup.');
        }
        if (! $hasTagihanTargets && ! $isTopup) {
            Log::critical("Manual payment guard mismatch: pembayaran id={$pembayaran->id} tidak ada target tagihan (hasTagihanTargets=false) maupun penanda topup (isTopup=false).");
            abort(500, 'Data pembayaran tidak konsisten: tidak ada target tagihan maupun penanda topup.');
        }

        DB::transaction(function () use ($manualPaymentRequest, $pembayaran, $reviewedByUserId) {
            $manualPaymentRequest->update([
                'status' => 'APPROVED',
                'reviewed_by' => $reviewedByUserId,
                'reviewed_at' => now(),
            ]);

            $pembayaran->update(['status' => 'lunas']);

            // Kasus bill-payment: alokasi terjadi DI DALAM transaction ini (pola
            // createCashPayment()), tidak ada Wallet::topup() yang terlibat sama sekali
            // untuk cabang ini.
            if ($pembayaran->topup_status === 'none') {
                $this->allocationService->allocate($pembayaran);
            }
        });

        // Kasus topup: Wallet::topup() dipanggil DI LUAR transaction, persis konvensi
        // webhook BRI — try/catch menandai topup_status completed/failed, TIDAK pernah
        // membungkus ulang topup() dalam transaction tambahan (ReconcilePayments sudah
        // menyediakan retry kalau langkah ini gagal, sama seperti jalur webhook).
        if ($isTopup) {
            $wallet = Wallet::where('siswa_id', $pembayaran->siswa_id)->first();

            if ($wallet !== null) {
                try {
                    $wallet->topup((float) $pembayaran->amount, $pembayaran, 'Topup via transfer manual disetujui');
                    $pembayaran->update(['topup_status' => 'completed']);
                } catch (AutoAllocationFailedException $e) {
                    // Saldo wallet SUDAH ter-kredit sukses (increment itu commit di dalam
                    // transaction internal Wallet::topup(), sebelum AutoAllocationEngine::run()
                    // dijalankan) -- hanya langkah auto-alokasi berikutnya yang gagal.
                    // topup_status wajib mencerminkan bahwa kreditnya sudah selesai, kalau
                    // tidak ReconcilePayments::retryFailedTopups() akan pilih ulang Pembayaran
                    // ini dan mengkredit wallet dua kali.
                    Log::error('Auto-alokasi gagal setelah topup manual payment berhasil di-kredit (saldo AMAN, hanya alokasi yang gagal): '.$e->getMessage());
                    $pembayaran->update(['topup_status' => 'completed']);
                } catch (\Throwable $e) {
                    Log::error('Gagal topup dari manual payment approval: '.$e->getMessage());
                    $pembayaran->update(['topup_status' => 'failed']);
                }
            } else {
                Log::error("Wallet tidak ditemukan saat approve manual topup payment: pembayaran id={$pembayaran->id}, siswa_id={$pembayaran->siswa_id}.");
                $pembayaran->update(['topup_status' => 'failed']);
            }
        }

        $siswa = $pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                $this->dispatcher->send($kontakUtama, new TransferManualDisetujuiNotification());
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDisetujuiNotification: '.$e->getMessage());
            }
        }
    }
}