<?php

namespace App\Http\Controllers\Admin;

use App\Models\ManualPaymentRequest;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\Wallet;
use App\Notifications\Finance\TransferManualDisetujuiNotification;
use App\Notifications\Finance\TransferManualDitolakNotification;
use App\Services\Finance\NotificationDispatcher;
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

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    // Siswa punya TenantScope global (BelongsToTenant) yang otomatis memfilter query
    // berdasarkan tenant user yang sedang login — artinya relasi ->siswa biasa akan
    // bernilai null (bukan siswa milik tenant lain) kalau diakses oleh admin dari
    // lembaga berbeda. Di sini kita justru BUTUH tahu lembaga_id sebenarnya (siswa
    // tenant manapun) supaya bisa dibandingkan secara eksplisit dengan lembagaId(),
    // makanya scope-nya sengaja di-bypass.
    private function siswaLembagaId(?int $siswaId): ?int
    {
        if ($siswaId === null) {
            return null;
        }

        return Siswa::withoutGlobalScope(TenantScope::class)->find($siswaId)?->lembaga_id;
    }

    public function approve(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $siswaLembagaId = $this->siswaLembagaId($manualPaymentRequest->pembayaran->siswa_id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

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
            } else {
                Log::error("Wallet tidak ditemukan saat approve manual topup payment: pembayaran id={$pembayaran->id}, siswa_id={$pembayaran->siswa_id}.");
                $pembayaran->update(['topup_status' => 'failed']);
            }
        }

        $siswa = $pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                app(NotificationDispatcher::class)->send($kontakUtama, new TransferManualDisetujuiNotification());
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDisetujuiNotification: '.$e->getMessage());
            }
        }

        return redirect()->back()->with('status', 'Transfer manual berhasil disetujui.');
    }

    public function reject(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $siswaLembagaId = $this->siswaLembagaId($manualPaymentRequest->pembayaran->siswa_id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

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

        $siswa = $manualPaymentRequest->pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                app(NotificationDispatcher::class)->send($kontakUtama, new TransferManualDitolakNotification($request->rejection_reason));
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDitolakNotification: '.$e->getMessage());
            }
        }

        return redirect()->back()->with('status', 'Transfer manual ditolak.');
    }
}
