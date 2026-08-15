<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusSiswa;
use App\Exports\VirtualAccountExport;
use App\Models\BriInboundPaymentLog;
use App\Models\BriVirtualAccount;
use App\Models\Kelas;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class VirtualAccountController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('pembayaran.virtual-account');

        $lembagaId = $this->lembagaId($request);
        $search = $request->input('search');
        $kelasId = $request->input('kelas_id');

        $query = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', function ($q) use ($lembagaId, $search, $kelasId) {
                $q->where('lembaga_id', $lembagaId);

                if ($search) {
                    $q->where(function ($q2) use ($search) {
                        $q2->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nis', 'like', "%{$search}%");
                    });
                }

                if ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                }
            })
            ->with(['wallet.siswa.kelas'])
            ->latest('created_at');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.virtual-account._daftar', [
                'vaList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        $totalVa = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->count();

        $totalSaldo = (float) BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->join('wallets', 'bri_virtual_accounts.wallet_id', '=', 'wallets.id')
            ->sum('wallets.balance');

        $totalBelumVa = Siswa::where('lembaga_id', $lembagaId)
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'))
            ->count();

        $kelasList = Kelas::where('lembaga_id', $lembagaId)
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();

        $kelasListGrouped = $kelasList->groupBy(fn ($k) => $k->tahunAjaran?->nama ?? 'Tanpa Tahun Ajaran');

        return view('admin.virtual-account.index', [
            'vaList' => $paginated,
            'perPage' => $perPage,
            'kelasList' => $kelasList,
            'kelasListGrouped' => $kelasListGrouped,
            'totalVa' => $totalVa,
            'totalSaldo' => $totalSaldo,
            'totalBelumVa' => $totalBelumVa,
        ]);
    }

    public function riwayat(Request $request, Siswa $siswa): View
    {
        $this->authorize('pembayaran.virtual-account');

        $siswaLembagaId = $this->siswaLembagaId($siswa->id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

        $va = BriVirtualAccount::where('wallet_id', $siswa->wallet?->id)
            ->where('va_type', 'WALLET_PERMANENT')
            ->first();

        $logs = $va
            ? BriInboundPaymentLog::where('va_number', $va->va_number)->latest()->get()
            : collect();

        return view('admin.virtual-account._riwayat-list', ['logs' => $logs]);
    }

    public function calonGenerate(Request $request): JsonResponse
    {
        $this->authorize('pembayaran.virtual-account');

        $lembagaId = $this->lembagaId($request);
        $search = $request->input('search');
        $kelasId = $request->input('kelas_id');

        $query = Siswa::where('lembaga_id', $lembagaId)
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'))
            ->with('kelas');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        $siswaList = $query->orderBy('nama_lengkap')->limit(200)->get();

        return response()->json([
            'data' => $siswaList->map(fn (Siswa $siswa) => [
                'id' => $siswa->id,
                'nama_lengkap' => $siswa->nama_lengkap,
                'nis' => $siswa->nis,
                'kelas' => $siswa->kelas->nama ?? '-',
            ]),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('pembayaran.virtual-account');

        $data = $request->validate([
            'mode' => ['required', 'in:semua,manual'],
            'siswa_ids' => ['required_if:mode,manual', 'array'],
            'siswa_ids.*' => ['integer'],
        ]);

        $lembagaId = $this->lembagaId($request);

        $query = Siswa::where('lembaga_id', $lembagaId)
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'));

        if ($data['mode'] === 'manual') {
            $query->whereIn('id', $data['siswa_ids'] ?? []);
        }

        $siswaList = $query->get();

        $berhasil = 0;
        $gagalNama = [];

        foreach ($siswaList as $siswa) {
            try {
                app(PaymentService::class)->getOrCreatePermanentVa($siswa);
                $berhasil++;
            } catch (\Throwable $e) {
                Log::error("Gagal generate VA untuk siswa id={$siswa->id}: ".$e->getMessage());
                $gagalNama[] = $siswa->nama_lengkap;
            }
        }

        $status = "{$berhasil} nomor VA berhasil dibuat.";
        if (count($gagalNama) > 0) {
            $status .= ' Gagal untuk: '.implode(', ', $gagalNama).'.';
        }

        return redirect()->route('admin.virtual-account.index')->with('status', $status);
    }

    public function export(Request $request)
    {
        $this->authorize('pembayaran.virtual-account');

        return Excel::download(new VirtualAccountExport($this->lembagaId($request)), 'virtual-account.xlsx');
    }

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    // Siswa punya TenantScope global (BelongsToTenant) yang otomatis memfilter query
    // berdasarkan tenant user yang sedang login. Method ini sengaja bypass scope itu
    // untuk mendapatkan lembaga_id SEBENARNYA dari siswa manapun (termasuk siswa
    // tenant lain), supaya bisa dibandingkan eksplisit dengan lembagaId() — pola
    // sama persis app/Http/Controllers/Admin/ManualPaymentController.php:86-93.
    private function siswaLembagaId(?int $siswaId): ?int
    {
        if ($siswaId === null) {
            return null;
        }

        return Siswa::withoutGlobalScope(TenantScope::class)->find($siswaId)?->lembaga_id;
    }
}
