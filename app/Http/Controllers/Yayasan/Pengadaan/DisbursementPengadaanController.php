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

        $yayasanId = $this->tenantContext->activeYayasanId();
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = PengajuanPengadaan::query()
            ->with(['lembaga', 'items'])
            ->where('yayasan_id', $yayasanId)
            ->whereIn('status', [StatusPengajuan::Approved, StatusPengajuan::Disbursed])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_pengajuan', 'like', "%{$search}%")
                        ->orWhere('judul_pengajuan', 'like', "%{$search}%");
                });
            })
            ->latest();

        $proposals = $query->paginate($perPage)->withQueryString();

        return view('portals.yayasan.pengadaan.disbursement.index', compact('proposals'));
    }

    public function store(StoreDisbursementRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
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
