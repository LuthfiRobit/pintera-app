<?php

namespace App\Http\Controllers\Yayasan\Pengadaan;

use App\Domains\Pengadaan\Actions\RecordDisbursementAction;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengadaan\StoreDisbursementRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisbursementPengadaanController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected RecordDisbursementAction $recordDisbursementAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('pengadaan.disbursement.manage');

        $yayasanId = $this->tenantContext->activeYayasanId() ?? \App\Models\Yayasan::first()?->id;
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $baseQuery = PengajuanPengadaan::query()
            ->with(['lembaga', 'items'])
            ->where('yayasan_id', $yayasanId)
            ->whereIn('status', [StatusPengajuan::Approved, StatusPengajuan::Disbursed]);

        $totalSiapCair = (clone $baseQuery)->where('status', StatusPengajuan::Approved)->count();
        $totalSudahCair = (clone $baseQuery)->where('status', StatusPengajuan::Disbursed)->count();
        $totalNominalCair = (clone $baseQuery)->where('status', StatusPengajuan::Disbursed)->sum('nominal_pencairan');

        $query = (clone $baseQuery)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nomor_pengajuan', 'like', "%{$search}%")
                        ->orWhere('judul_pengajuan', 'like', "%{$search}%")
                        ->orWhereHas('lembaga', fn ($lem) => $lem->where('nama', 'like', "%{$search}%"));
                });
            })
            ->latest();

        $proposals = $query->paginate($perPage)->withQueryString();

        if ($request->ajax()) {
            return view('portals.yayasan.pengadaan.disbursement._daftar', compact('proposals'));
        }

        return view('portals.yayasan.pengadaan.disbursement.index', compact('proposals', 'totalSiapCair', 'totalSudahCair', 'totalNominalCair', 'perPage'));
    }

    public function store(StoreDisbursementRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        $this->authorize('pengadaan.disbursement.manage');
        abort_unless($proposal->yayasan_id === $this->tenantContext->activeYayasanId(), 404);

        $buktiPath = null;
        if ($request->hasFile('bukti_transfer')) {
            $buktiPath = $request->file('bukti_transfer')->store('pengadaan/pencairan', 'public');
        }

        $dto = $request->toDTO($buktiPath);
        $this->recordDisbursementAction->execute($proposal, $dto);

        return redirect()->route('admin.pengadaan.disbursement.index')
            ->with('success', "Pencairan dana untuk pengajuan [{$proposal->nomor_pengajuan}] berhasil dicatat!");
    }
}
