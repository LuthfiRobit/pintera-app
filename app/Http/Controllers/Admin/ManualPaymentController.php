<?php

namespace App\Http\Controllers\Admin;

use App\Models\ManualPaymentRequest;
use App\Models\Wallet;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManualPaymentController extends BaseController
{
    use AuthorizesRequests;

    public function approve(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

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
            abort(500, 'Data pembayaran tidak konsisten: punya target tagihan sekaligus ditandai topup.');
        }
        if (! $hasTagihanTargets && ! $isTopup) {
            abort(500, 'Data pembayaran tidak konsisten: tidak ada target tagihan maupun penanda topup.');
        }

        DB::transaction(function () use ($manualPaymentRequest, $pembayaran, $request) {
            $manualPaymentRequest->update([
                'status' => 'APPROVED',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $pembayaran->update(['status' => 'lunas']);

            // Kasus bill-payment: alokasi terjadi DI DALAM transaction ini (pola
            // createCashPayment()), tidak ada Wallet::topup() yang terlibat sama sekali
            // untuk cabang ini.
            if ($pembayaran->topup_status === 'none') {
                app(PaymentAllocationService::class)->allocate($pembayaran);
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
                } catch (\Throwable $e) {
                    Log::error('Gagal topup dari manual payment approval: '.$e->getMessage());
                    $pembayaran->update(['topup_status' => 'failed']);
                }
            }
        }

        return redirect()->back()->with('status', 'Transfer manual berhasil disetujui.');
    }

    public function reject(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        if ($manualPaymentRequest->status !== 'PENDING') {
            abort(422, 'Permintaan ini sudah diproses sebelumnya.');
        }

        $request->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        DB::transaction(function () use ($manualPaymentRequest, $request) {
            $manualPaymentRequest->update([
                'status' => 'REJECTED',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Reject tidak pernah memicu Wallet::topup() — baik kasus bill maupun topup,
            // ditolak berarti tidak ada dana yang masuk sama sekali, cukup ubah status.
            $manualPaymentRequest->pembayaran->update(['status' => 'ditolak']);
        });

        return redirect()->back()->with('status', 'Transfer manual ditolak.');
    }
}
