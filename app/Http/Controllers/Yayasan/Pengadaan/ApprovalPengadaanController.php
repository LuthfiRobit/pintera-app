<?php

namespace App\Http\Controllers\Yayasan\Pengadaan;

use App\Domains\Pengadaan\Actions\ProcessProposalApprovalAction;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Shared\Context\TenantContext;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengadaan\ProcessApprovalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalPengadaanController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected ProcessProposalApprovalAction $processApprovalAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('pengadaan.approval.yayasan');

        $yayasanId = $this->tenantContext->activeYayasanId() ?? \App\Models\Yayasan::first()?->id;
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $baseQuery = PengajuanPengadaan::query()
            ->with(['lembaga', 'items', 'approvalRequest.currentStep'])
            ->where('yayasan_id', $yayasanId)
            ->whereIn('status', [StatusPengajuan::Submitted, StatusPengajuan::InReview]);

        $totalPendingReview = (clone $baseQuery)->count();
        $totalMendesak = (clone $baseQuery)->where('tingkat_urgensi', '!=', 'biasa')->count();
        $totalNilaiPending = (clone $baseQuery)->sum('total_estimasi');

        $query = (clone $baseQuery)
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
            return view('portals.yayasan.pengadaan.inbox._daftar', compact('proposals'));
        }

        return view('portals.yayasan.pengadaan.inbox.index', compact('proposals', 'totalPendingReview', 'totalMendesak', 'totalNilaiPending', 'perPage'));
    }

    public function review(PengajuanPengadaan $proposal): View
    {
        $this->authorizeApprover('mereview');
        abort_unless($proposal->yayasan_id === $this->tenantContext->activeYayasanId(), 404);

        $proposal->load(['lembaga', 'pengaju', 'items.kategori', 'items.ruangan', 'approvalRequest.logs.user', 'approvalRequest.currentStep']);

        return view('portals.yayasan.pengadaan.inbox.review', compact('proposal'));
    }

    public function decision(ProcessApprovalRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        $this->authorizeApprover('memberikan keputusan');
        abort_unless($proposal->yayasan_id === $this->tenantContext->activeYayasanId(), 404);

        $action = ApprovalAction::from($request->validated()['action']);
        $itemDecisions = $request->validated()['item_decisions'] ?? [];
        $notes = $request->validated()['notes'] ?? null;

        $this->processApprovalAction->execute(
            proposal: $proposal,
            user: $request->user(),
            action: $action,
            itemDecisions: $itemDecisions,
            notes: $notes
        );

        $msg = match ($action) {
            ApprovalAction::Approve => 'Persetujuan langkah workflow berhasil diproses.',
            ApprovalAction::Reject => 'Proposal berhasil ditolak.',
            ApprovalAction::RequestRevision => 'Permintaan revisi berhasil dikirimkan ke pengaju.',
        };

        if ($request->user()->widestScopeLevel() === 'lembaga') {
            return redirect()->route('admin.pengadaan.proposal.show', $proposal)->with('success', $msg);
        }

        return redirect()->route('admin.pengadaan.inbox.index')->with('success', $msg);
    }

    private function authorizeApprover(string $aksi): void
    {
        $user = auth()->user();

        abort_unless(
            $user->can('pengadaan.approval.yayasan') || $user->can('pengadaan.approval.internal') || $user->hasRole(['super_admin', 'yayasan_super_admin']),
            403,
            "Anda tidak memiliki hak akses untuk {$aksi} pengajuan ini."
        );
    }
}
