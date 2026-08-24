<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Pembayaran\ApproveManualPaymentAction;
use App\Domains\Keuangan\Actions\Pembayaran\RejectManualPaymentAction;
use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualPaymentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('pembayaran.verifikasi');

        $lembagaId = $this->lembagaId($request);

        $query = ManualPaymentRequest::where('status', 'PENDING')
            ->whereHas('pembayaran', function ($q) use ($lembagaId) {
                $q->whereHas('siswa', fn ($q2) => $q2->where('lembaga_id', $lembagaId));
            })
            ->with(['pembayaran.siswa', 'pembayaran.pembayaranTagihan', 'requestedBy'])
            ->latest('transfer_date');

        if ($search = $request->input('search')) {
            $query->whereHas('pembayaran.siswa', fn ($q) => $q->where('nama_lengkap', 'like', '%'.$search.'%'));
        }

        if ($dari = $request->input('dari')) {
            $query->where('transfer_date', '>=', $dari);
        }

        if ($sampai = $request->input('sampai')) {
            $query->where('transfer_date', '<=', $sampai);
        }

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.manual-payment._daftar', [
                'requestList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        return view('portals.lembaga.keuangan.manual-payment.index', [
            'requestList' => $paginated,
            'perPage' => $perPage,
            'totalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
                ->count(),
            'totalNominalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
                ->sum('amount'),
        ]);
    }

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

    public function approve(Request $request, ManualPaymentRequest $manualPaymentRequest, ApproveManualPaymentAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $siswaLembagaId = $this->siswaLembagaId($manualPaymentRequest->pembayaran->siswa_id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

        $action->execute($manualPaymentRequest, $request->user()->id);

        return redirect()->back()->with('status', 'Transfer manual berhasil disetujui.');
    }

    public function reject(Request $request, ManualPaymentRequest $manualPaymentRequest, RejectManualPaymentAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $siswaLembagaId = $this->siswaLembagaId($manualPaymentRequest->pembayaran->siswa_id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

        $request->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        $action->execute($manualPaymentRequest, $request->user()->id, $request->rejection_reason);

        return redirect()->back()->with('status', 'Transfer manual ditolak.');
    }
}