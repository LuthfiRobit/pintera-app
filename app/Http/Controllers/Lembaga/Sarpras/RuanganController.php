<?php

namespace App\Http\Controllers\Lembaga\Sarpras;

use App\Domains\Sarpras\Actions\CreateRuanganAction;
use App\Domains\Sarpras\Actions\UpdateRuanganAction;
use App\Domains\Sarpras\DataTransferObjects\RuanganData;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sarpras\StoreRuanganRequest;
use App\Models\Guru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RuanganController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected CreateRuanganAction $createAction,
        protected UpdateRuanganAction $updateAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('sarpras.ruangan.view');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = Ruangan::query()
            ->with(['gedung', 'penanggungJawab'])
            ->withCount('aset')
            ->where(function ($query) use ($lembagaId, $yayasanId) {
                if ($lembagaId) {
                    $query->where('lembaga_id', $lembagaId)
                        ->orWhere(function ($q) use ($yayasanId) {
                            $q->where('yayasan_id', $yayasanId)->where('is_shared', true);
                        });
                } elseif ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
            })
            ->when($request->gedung_id, fn ($q, $gId) => $q->where('gedung_id', $gId))
            ->when($request->jenis_ruangan, fn ($q, $jenis) => $q->where('jenis_ruangan', $jenis))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_ruangan', 'like', "%{$search}%")
                        ->orWhere('kode_ruangan', 'like', "%{$search}%");
                });
            })
            ->orderBy('kode_ruangan');

        $ruanganList = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.sarpras.ruangan._daftar', [
                'ruanganList' => $ruanganList,
                'perPage' => $perPage,
            ]);
        }

        $gedungOptions = Gedung::where('lembaga_id', $lembagaId)->orWhere('yayasan_id', $yayasanId)->get();
        $totalRuangan = Ruangan::where('lembaga_id', $lembagaId)->count();
        $totalKelas = Ruangan::where('lembaga_id', $lembagaId)->where('jenis_ruangan', JenisRuangan::KelasTeori)->count();
        $totalLab = Ruangan::where('lembaga_id', $lembagaId)->where('jenis_ruangan', JenisRuangan::Laboratorium)->count();
        $totalShared = Ruangan::where('lembaga_id', $lembagaId)->where('is_shared', true)->count();

        return view('portals.lembaga.sarpras.ruangan.index', [
            'ruanganList' => $ruanganList,
            'gedungOptions' => $gedungOptions,
            'jenisOptions' => JenisRuangan::cases(),
            'perPage' => $perPage,
            'totalRuangan' => $totalRuangan,
            'totalKelas' => $totalKelas,
            'totalLab' => $totalLab,
            'totalShared' => $totalShared,
        ]);
    }

    public function create(): View
    {
        $this->authorize('sarpras.ruangan.manage');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $gedungOptions = Gedung::where('lembaga_id', $lembagaId)->orWhere('yayasan_id', $yayasanId)->get();
        $guruOptions = Guru::where('lembaga_id', $lembagaId)->get();

        return view('portals.lembaga.sarpras.ruangan.form', [
            'ruangan' => new Ruangan(),
            'gedungOptions' => $gedungOptions,
            'guruOptions' => $guruOptions,
            'jenisOptions' => JenisRuangan::cases(),
            'isEdit' => false,
        ]);
    }

    public function store(StoreRuanganRequest $request): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $dto = RuanganData::fromArray($request->validated(), $yayasanId, $lembagaId);
        $this->createAction->execute($dto);

        return redirect()->route('admin.sarpras.ruangan.index')
            ->with('status', 'Ruangan berhasil ditambahkan.');
    }

    public function show(Ruangan $ruangan): View
    {
        $this->authorize('sarpras.ruangan.view');

        $ruangan->load(['gedung', 'penanggungJawab', 'aset.kategori', 'jadwalPelajaran.mataPelajaran', 'jadwalPelajaran.guru']);

        return view('portals.lembaga.sarpras.ruangan.show', compact('ruangan'));
    }

    public function edit(Ruangan $ruangan): View
    {
        $this->authorize('sarpras.ruangan.manage');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $gedungOptions = Gedung::where('lembaga_id', $lembagaId)->orWhere('yayasan_id', $yayasanId)->get();
        $guruOptions = Guru::where('lembaga_id', $lembagaId)->get();

        return view('portals.lembaga.sarpras.ruangan.form', [
            'ruangan' => $ruangan,
            'gedungOptions' => $gedungOptions,
            'guruOptions' => $guruOptions,
            'jenisOptions' => JenisRuangan::cases(),
            'isEdit' => true,
        ]);
    }

    public function update(StoreRuanganRequest $request, Ruangan $ruangan): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $dto = RuanganData::fromArray($request->validated(), $yayasanId, $lembagaId);
        $this->updateAction->execute($ruangan, $dto);

        return redirect()->route('admin.sarpras.ruangan.index')
            ->with('status', 'Data ruangan berhasil diperbarui.');
    }

    public function destroy(Ruangan $ruangan): RedirectResponse
    {
        $this->authorize('sarpras.ruangan.manage');

        if ($ruangan->aset()->exists()) {
            return back()->withErrors(['error' => 'Ruangan tidak dapat dihapus karena masih terdapat aset di dalamnya.']);
        }

        $ruangan->delete();

        return redirect()->route('admin.sarpras.ruangan.index')
            ->with('status', 'Ruangan berhasil dihapus.');
    }
}
