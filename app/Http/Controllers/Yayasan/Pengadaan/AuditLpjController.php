<?php

namespace App\Http\Controllers\Yayasan\Pengadaan;

use App\Domains\Pengadaan\Actions\VerifyLpjAction;
use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLpjController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected VerifyLpjAction $verifyLpjAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('pengadaan.lpj.verify');

        $yayasanId = $this->tenantContext->activeYayasanId() ?? \App\Models\Yayasan::first()?->id;
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $baseQuery = LpjPengadaan::query()
            ->with(['proposal.lembaga', 'items.pengajuanItem'])
            ->whereHas('proposal', fn ($q) => $q->where('yayasan_id', $yayasanId));

        $totalMenungguAudit = (clone $baseQuery)->where('status_lpj', StatusLpj::Submitted)->count();
        $totalTerverifikasi = (clone $baseQuery)->where('status_lpj', StatusLpj::Verified)->count();
        $totalPerluRevisi = (clone $baseQuery)->where('status_lpj', StatusLpj::RevisionRequired)->count();

        $query = (clone $baseQuery)
            ->when($request->status, fn ($q, $s) => $q->where('status_lpj', $s))
            ->when($request->search, function ($q, $search) {
                $q->whereHas('proposal', function ($p) use ($search) {
                    $p->where('nomor_pengajuan', 'like', "%{$search}%")
                        ->orWhere('judul_pengajuan', 'like', "%{$search}%")
                        ->orWhereHas('lembaga', fn ($lem) => $lem->where('nama', 'like', "%{$search}%"));
                });
            })
            ->latest();

        $lpjList = $query->paginate($perPage)->withQueryString();

        if ($request->ajax()) {
            return view('portals.yayasan.pengadaan.audit-lpj._daftar', compact('lpjList'));
        }

        return view('portals.yayasan.pengadaan.audit-lpj.index', compact('lpjList', 'totalMenungguAudit', 'totalTerverifikasi', 'totalPerluRevisi', 'perPage'));
    }

    public function show(LpjPengadaan $lpj): View
    {
        $this->authorize('pengadaan.lpj.verify');
        $lpj->load(['proposal.lembaga', 'items.pengajuanItem.kategori', 'items.pengajuanItem.ruangan', 'verifiedBy']);

        return view('portals.yayasan.pengadaan.audit-lpj.show', compact('lpj'));
    }

    public function verify(Request $request, LpjPengadaan $lpj): RedirectResponse
    {
        $this->authorize('pengadaan.lpj.verify');

        $request->validate([
            'is_approved' => ['required', 'boolean'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->verifyLpjAction->execute(
            lpj: $lpj,
            verifierUserId: (int) $request->user()->id,
            isApproved: $request->boolean('is_approved'),
            notes: $request->input('catatan_verifikasi')
        );

        $statusMsg = $request->boolean('is_approved') ? 'diverifikasi dan disetujui' : 'diminta revisi perbaikan nota';

        return redirect()->route('admin.pengadaan.audit-lpj.index')
            ->with('success', "LPJ berhasil {$statusMsg}.");
    }
}
