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

        $yayasanId = $this->tenantContext->activeYayasanId();
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = PengajuanPengadaan::query()
            ->with(['lembaga', 'items', 'approvalRequest.currentStep'])
            ->where('yayasan_id', $yayasanId)
            ->whereIn('status', [StatusPengajuan::Submitted, StatusPengajuan::InReview])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_pengajuan', 'like', "%{$search}%")
                        ->orWhere('judul_pengajuan', 'like', "%{$search}%");
                });
            })
            ->latest();

        $proposals = $query->paginate($perPage)->withQueryString();

        if ($request->ajax()) {
            return view('portals.yayasan.pengadaan.inbox._daftar', compact('proposals'));
        }

        return view('portals.yayasan.pengadaan.inbox.index', compact('proposals'));
    }

    public function review(PengajuanPengadaan $proposal): View
    {
        $this->authorize('pengadaan.approval.yayasan');
        $proposal->load(['lembaga', 'pengaju', 'items.kategori', 'items.ruangan', 'approvalRequest.logs.user', 'approvalRequest.currentStep']);

        return view('portals.yayasan.pengadaan.inbox.review', compact('proposal'));
    }

    public function decision(ProcessApprovalRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
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
            ApprovalAction::Approve => 'Proposal berhasil disetujui.',
            ApprovalAction::Reject => 'Proposal berhasil ditolak.',
            ApprovalAction::RequestRevision => 'Permintaan revisi berhasil dikirimkan ke pengaju.',
        };

        return redirect()->route('admin.pengadaan.inbox.index')->with('success', $msg);
    }
}
