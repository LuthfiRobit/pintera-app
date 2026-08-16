<?php

namespace App\Http\Controllers\Lembaga\Pengadaan;

use App\Domains\Pengadaan\Actions\CreatePengajuanAction;
use App\Domains\Pengadaan\Actions\SubmitPengajuanAction;
use App\Domains\Pengadaan\Actions\UpdatePengajuanAction;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengadaan\StorePengajuanRequest;
use App\Http\Requests\Pengadaan\UpdatePengajuanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PengajuanPengadaanController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected CreatePengajuanAction $createAction,
        protected UpdatePengajuanAction $updateAction,
        protected SubmitPengajuanAction $submitAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('pengadaan.proposal.view');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = PengajuanPengadaan::query()
            ->with(['items', 'approvalRequest.currentStep'])
            ->where(function ($query) use ($lembagaId, $yayasanId) {
                if ($lembagaId) {
                    $query->where('lembaga_id', $lembagaId);
                } elseif ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->urgensi, fn ($q, $u) => $q->where('tingkat_urgensi', $u))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_pengajuan', 'like', "%{$search}%")
                        ->orWhere('judul_pengajuan', 'like', "%{$search}%");
                });
            })
            ->latest();

        $proposals = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->count(),
            'draft' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->where('status', StatusPengajuan::Draft)->count(),
            'in_review' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->whereIn('status', [StatusPengajuan::Submitted, StatusPengajuan::InReview])->count(),
            'disbursed' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->where('status', StatusPengajuan::Disbursed)->count(),
            'completed' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->where('status', StatusPengajuan::Completed)->count(),
        ];

        if ($request->ajax()) {
            return view('portals.lembaga.pengadaan.proposal._daftar', compact('proposals'));
        }

        return view('portals.lembaga.pengadaan.proposal.index', compact('proposals', 'stats'));
    }

    public function create(): View
    {
        $this->authorize('pengadaan.proposal.create');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $kategoris = KategoriAset::where(function ($q) use ($lembagaId, $yayasanId) {
            if ($lembagaId) $q->where('lembaga_id', $lembagaId);
            elseif ($yayasanId) $q->where('yayasan_id', $yayasanId);
        })->get();

        $ruangans = Ruangan::where(function ($q) use ($lembagaId, $yayasanId) {
            if ($lembagaId) $q->where('lembaga_id', $lembagaId);
            elseif ($yayasanId) $q->where('yayasan_id', $yayasanId);
        })->get();

        return view('portals.lembaga.pengadaan.proposal.create', compact('kategoris', 'ruangans'));
    }

    public function store(StorePengajuanRequest $request): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $items = $request->validated()['items'];
        foreach ($items as $idx => $item) {
            if ($request->hasFile("items.{$idx}.foto_referensi")) {
                $items[$idx]['foto_referensi_path'] = $request->file("items.{$idx}.foto_referensi")->store('pengadaan/referensi', 'public');
            }
        }

        $dto = $request->toDTO($yayasanId, $lembagaId);
        $dto = new \App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData(
            lembagaId: $dto->lembagaId,
            yayasanId: $dto->yayasanId,
            judulPengajuan: $dto->judulPengajuan,
            latarBelakang: $dto->latarBelakang,
            tingkatUrgensi: $dto->tingkatUrgensi,
            items: $items
        );

        $proposal = $this->createAction->execute($dto, (int) $request->user()->id);

        if ($request->boolean('submit_immediately')) {
            $this->submitAction->execute($proposal);
            return redirect()->route('admin.pengadaan.proposal.show', $proposal)
                ->with('success', 'Proposal pengadaan berhasil diajukan untuk ditinjau.');
        }

        return redirect()->route('admin.pengadaan.proposal.show', $proposal)
            ->with('success', 'Draft proposal pengadaan berhasil disimpan.');
    }

    public function show(PengajuanPengadaan $proposal): View
    {
        $this->authorize('pengadaan.proposal.view');
        $proposal->load(['items.kategori', 'items.ruangan', 'approvalRequest.logs.user', 'approvalRequest.currentStep', 'lpj.items']);

        return view('portals.lembaga.pengadaan.proposal.show', compact('proposal'));
    }

    public function edit(PengajuanPengadaan $proposal): View
    {
        $this->authorize('pengadaan.proposal.edit');

        if (! in_array($proposal->status, [StatusPengajuan::Draft, StatusPengajuan::RevisionRequired])) {
            abort(403, 'Usulan ini tidak dalam status yang dapat diedit.');
        }

        $proposal->load(['items.kategori', 'items.ruangan', 'approvalRequest.logs']);

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $kategoris = KategoriAset::where(function ($q) use ($lembagaId, $yayasanId) {
            if ($lembagaId) $q->where('lembaga_id', $lembagaId);
            elseif ($yayasanId) $q->where('yayasan_id', $yayasanId);
        })->get();

        $ruangans = Ruangan::where(function ($q) use ($lembagaId, $yayasanId) {
            if ($lembagaId) $q->where('lembaga_id', $lembagaId);
            elseif ($yayasanId) $q->where('yayasan_id', $yayasanId);
        })->get();

        return view('portals.lembaga.pengadaan.proposal.edit', compact('proposal', 'kategoris', 'ruangans'));
    }

    public function update(UpdatePengajuanRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        $yayasanId = $proposal->yayasan_id;
        $lembagaId = $proposal->lembaga_id;

        $items = $request->validated()['items'];
        foreach ($items as $idx => $item) {
            if ($request->hasFile("items.{$idx}.foto_referensi")) {
                $items[$idx]['foto_referensi_path'] = $request->file("items.{$idx}.foto_referensi")->store('pengadaan/referensi', 'public');
            }
        }

        $dto = $request->toDTO($yayasanId, $lembagaId);
        $dto = new \App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData(
            lembagaId: $dto->lembagaId,
            yayasanId: $dto->yayasanId,
            judulPengajuan: $dto->judulPengajuan,
            latarBelakang: $dto->latarBelakang,
            tingkatUrgensi: $dto->tingkatUrgensi,
            items: $items
        );

        $this->updateAction->execute($proposal, $dto);

        if ($request->boolean('submit_immediately')) {
            $this->submitAction->execute($proposal);
            return redirect()->route('admin.pengadaan.proposal.show', $proposal)
                ->with('success', 'Proposal pengadaan berhasil diperbarui dan diajukan ulang untuk ditinjau.');
        }

        return redirect()->route('admin.pengadaan.proposal.show', $proposal)
            ->with('success', 'Perubahan proposal pengadaan berhasil disimpan.');
    }

    public function submit(PengajuanPengadaan $proposal): RedirectResponse
    {
        $this->authorize('pengadaan.proposal.create');
        $this->submitAction->execute($proposal);

        return redirect()->route('admin.pengadaan.proposal.show', $proposal)
            ->with('success', 'Proposal pengadaan berhasil diajukan untuk ditinjau.');
    }
}
