<?php

namespace App\Http\Controllers\Lembaga\Sarpras;

use App\Domains\Sarpras\Actions\CreateKategoriAsetAction;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sarpras\StoreKategoriAsetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KategoriAsetController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected CreateKategoriAsetAction $createAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('sarpras.kategori.view');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = KategoriAset::query()
            ->withCount('aset')
            ->where(function ($query) use ($lembagaId, $yayasanId) {
                if ($lembagaId) {
                    $query->where('lembaga_id', $lembagaId);
                } elseif ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_kategori', 'like', "%{$search}%")
                        ->orWhere('kode_kategori', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_kategori');

        $kategoriList = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.sarpras.kategori._daftar', [
                'kategoriList' => $kategoriList,
                'perPage' => $perPage,
            ]);
        }

        $totalKategori = KategoriAset::where('lembaga_id', $lembagaId)->count();
        $totalAset = \App\Domains\Sarpras\Models\AsetBarang::where('lembaga_id', $lembagaId)->count();

        return view('portals.lembaga.sarpras.kategori.index', [
            'kategoriList' => $kategoriList,
            'perPage' => $perPage,
            'totalKategori' => $totalKategori,
            'totalAset' => $totalAset,
        ]);
    }

    public function store(StoreKategoriAsetRequest $request): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $dto = $request->toDTO($yayasanId, $lembagaId);
        $this->createAction->execute($dto);

        return redirect()->route('admin.sarpras.kategori.index')
            ->with('status', 'Kategori aset berhasil ditambahkan.');
    }

    public function destroy(KategoriAset $kategori): RedirectResponse
    {
        $this->authorize('sarpras.kategori.manage');

        if ($kategori->aset()->exists()) {
            return back()->withErrors(['error' => 'Kategori tidak dapat dihapus karena masih digunakan oleh aset aktif.']);
        }

        $kategori->delete();

        return redirect()->route('admin.sarpras.kategori.index')
            ->with('status', 'Kategori aset berhasil dihapus.');
    }
}
