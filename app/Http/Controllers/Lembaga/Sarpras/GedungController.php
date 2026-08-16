<?php

namespace App\Http\Controllers\Lembaga\Sarpras;

use App\Domains\Sarpras\Actions\CreateGedungAction;
use App\Domains\Sarpras\Actions\UpdateGedungAction;
use App\Domains\Sarpras\DataTransferObjects\GedungData;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sarpras\StoreGedungRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GedungController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected CreateGedungAction $createAction,
        protected UpdateGedungAction $updateAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('sarpras.gedung.view');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = Gedung::query()
            ->withCount('ruangan')
            ->where(function ($query) use ($lembagaId, $yayasanId) {
                if ($lembagaId) {
                    $query->where('lembaga_id', $lembagaId);
                } elseif ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_gedung', 'like', "%{$search}%")
                        ->orWhere('kode_gedung', 'like', "%{$search}%");
                });
            })
            ->orderBy('kode_gedung');

        $gedungList = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.sarpras.gedung._daftar', [
                'gedungList' => $gedungList,
                'perPage' => $perPage,
            ]);
        }

        $totalGedung = Gedung::where('lembaga_id', $lembagaId)->count();
        $totalLantai = (int) Gedung::where('lembaga_id', $lembagaId)->sum('jumlah_lantai');
        $totalRuangan = \App\Domains\Sarpras\Models\Ruangan::where('lembaga_id', $lembagaId)->count();

        return view('portals.lembaga.sarpras.gedung.index', [
            'gedungList' => $gedungList,
            'perPage' => $perPage,
            'totalGedung' => $totalGedung,
            'totalLantai' => $totalLantai,
            'totalRuangan' => $totalRuangan,
        ]);
    }

    public function create(): View
    {
        $this->authorize('sarpras.gedung.manage');

        return view('portals.lembaga.sarpras.gedung.form', [
            'gedung' => new Gedung(),
            'isEdit' => false,
        ]);
    }

    public function store(StoreGedungRequest $request): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $dto = GedungData::fromArray($request->validated(), $yayasanId, $lembagaId);
        $this->createAction->execute($dto);

        return redirect()->route('admin.sarpras.gedung.index')
            ->with('status', 'Gedung berhasil ditambahkan.');
    }

    public function edit(Gedung $gedung): View
    {
        $this->authorize('sarpras.gedung.manage');

        return view('portals.lembaga.sarpras.gedung.form', [
            'gedung' => $gedung,
            'isEdit' => true,
        ]);
    }

    public function update(StoreGedungRequest $request, Gedung $gedung): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $dto = GedungData::fromArray($request->validated(), $yayasanId, $lembagaId);
        $this->updateAction->execute($gedung, $dto);

        return redirect()->route('admin.sarpras.gedung.index')
            ->with('status', 'Data gedung berhasil diperbarui.');
    }

    public function destroy(Gedung $gedung): RedirectResponse
    {
        $this->authorize('sarpras.gedung.manage');

        if ($gedung->ruangan()->exists()) {
            return back()->withErrors(['error' => 'Gedung tidak dapat dihapus karena masih memiliki ruangan aktif.']);
        }

        $gedung->delete();

        return redirect()->route('admin.sarpras.gedung.index')
            ->with('status', 'Gedung berhasil dihapus.');
    }
}
