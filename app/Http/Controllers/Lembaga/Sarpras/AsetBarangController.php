<?php

namespace App\Http\Controllers\Lembaga\Sarpras;

use App\Domains\Sarpras\Actions\CreateAsetBarangAction;
use App\Domains\Sarpras\Actions\UpdateAsetBarangAction;
use App\Domains\Sarpras\DataTransferObjects\AsetBarangData;
use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sarpras\StoreAsetBarangRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AsetBarangController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected CreateAsetBarangAction $createAction,
        protected UpdateAsetBarangAction $updateAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('sarpras.aset.view');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = AsetBarang::query()
            ->with(['kategori', 'ruangan.gedung'])
            ->where(function ($query) use ($lembagaId, $yayasanId) {
                if ($lembagaId) {
                    $query->where('lembaga_id', $lembagaId);
                } elseif ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
            })
            ->when($request->kategori_id, fn ($q, $kId) => $q->where('kategori_aset_id', $kId))
            ->when($request->ruangan_id, fn ($q, $rId) => $q->where('ruangan_id', $rId))
            ->when($request->kondisi, fn ($q, $k) => $q->where('kondisi', $k))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_barang', 'like', "%{$search}%")
                        ->orWhere('kode_inventaris', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%");
                });
            })
            ->latest();

        $asetList = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.sarpras.aset._daftar', [
                'asetList' => $asetList,
                'perPage' => $perPage,
            ]);
        }

        $kategoriOptions = KategoriAset::where('lembaga_id', $lembagaId)->orWhere('yayasan_id', $yayasanId)->get();
        $ruanganOptions = Ruangan::where('lembaga_id', $lembagaId)->orWhere('yayasan_id', $yayasanId)->get();

        $statsQuery = AsetBarang::where(function ($query) use ($lembagaId, $yayasanId) {
            if ($lembagaId) {
                $query->where('lembaga_id', $lembagaId);
            } elseif ($yayasanId) {
                $query->where('yayasan_id', $yayasanId);
            }
        });

        $totalItem = (clone $statsQuery)->sum('qty');
        $totalNilai = (clone $statsQuery)->sum('harga_perolehan');
        $totalBaik = (clone $statsQuery)->where('kondisi', KondisiAset::Baik)->sum('qty');
        $totalRusak = (clone $statsQuery)->whereIn('kondisi', [KondisiAset::RusakRingan, KondisiAset::RusakBerat])->sum('qty');

        return view('portals.lembaga.sarpras.aset.index', [
            'asetList' => $asetList,
            'kategoriOptions' => $kategoriOptions,
            'ruanganOptions' => $ruanganOptions,
            'kondisiOptions' => KondisiAset::cases(),
            'perPage' => $perPage,
            'totalItem' => $totalItem,
            'totalNilai' => $totalNilai,
            'totalBaik' => $totalBaik,
            'totalRusak' => $totalRusak,
        ]);
    }

    public function create(): View
    {
        $this->authorize('sarpras.aset.manage');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $kategoriOptions = KategoriAset::where('lembaga_id', $lembagaId)->orWhere('yayasan_id', $yayasanId)->get();
        $ruanganOptions = Ruangan::where('lembaga_id', $lembagaId)->orWhere('yayasan_id', $yayasanId)->get();

        return view('portals.lembaga.sarpras.aset.form', [
            'aset' => new AsetBarang(),
            'kategoriOptions' => $kategoriOptions,
            'ruanganOptions' => $ruanganOptions,
            'kondisiOptions' => KondisiAset::cases(),
            'tipeOptions' => TipePencatatanAset::cases(),
            'sumberOptions' => SumberPerolehanAset::cases(),
            'isEdit' => false,
        ]);
    }

    public function store(StoreAsetBarangRequest $request): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $validated = $request->validated();
        if ($request->hasFile('foto')) {
            $validated['foto_barang_path'] = $request->file('foto')->store('sarpras/aset', 'public');
        }

        $dto = AsetBarangData::fromArray($validated, $yayasanId, $lembagaId);
        $this->createAction->execute($dto);

        return redirect()->route('admin.sarpras.aset.index')
            ->with('status', 'Aset barang berhasil ditambahkan.');
    }

    public function show(AsetBarang $aset): View
    {
        $this->authorize('sarpras.aset.view');

        $aset->load(['kategori', 'ruangan.gedung', 'riwayatMutasi.ruanganAsal', 'riwayatMutasi.ruanganTujuan', 'riwayatMutasi.dilakukanOleh']);
        $ruanganOptions = Ruangan::where('lembaga_id', $aset->lembaga_id)->orWhere('yayasan_id', $aset->yayasan_id)->get();

        return view('portals.lembaga.sarpras.aset.show', compact('aset', 'ruanganOptions'));
    }

    public function edit(AsetBarang $aset): View
    {
        $this->authorize('sarpras.aset.manage');

        $kategoriOptions = KategoriAset::where('lembaga_id', $aset->lembaga_id)->orWhere('yayasan_id', $aset->yayasan_id)->get();
        $ruanganOptions = Ruangan::where('lembaga_id', $aset->lembaga_id)->orWhere('yayasan_id', $aset->yayasan_id)->get();

        return view('portals.lembaga.sarpras.aset.form', [
            'aset' => $aset,
            'kategoriOptions' => $kategoriOptions,
            'ruanganOptions' => $ruanganOptions,
            'kondisiOptions' => KondisiAset::cases(),
            'tipeOptions' => TipePencatatanAset::cases(),
            'sumberOptions' => SumberPerolehanAset::cases(),
            'isEdit' => true,
        ]);
    }

    public function update(StoreAsetBarangRequest $request, AsetBarang $aset): RedirectResponse
    {
        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $validated = $request->validated();
        if ($request->hasFile('foto')) {
            if ($aset->foto_barang_path && Storage::disk('public')->exists($aset->foto_barang_path)) {
                Storage::disk('public')->delete($aset->foto_barang_path);
            }
            $validated['foto_barang_path'] = $request->file('foto')->store('sarpras/aset', 'public');
        }

        $dto = AsetBarangData::fromArray($validated, $yayasanId, $lembagaId);
        $this->updateAction->execute($aset, $dto);

        return redirect()->route('admin.sarpras.aset.index')
            ->with('status', 'Data aset barang berhasil diperbarui.');
    }

    public function destroy(AsetBarang $aset): RedirectResponse
    {
        $this->authorize('sarpras.aset.manage');

        if ($aset->foto_barang_path && Storage::disk('public')->exists($aset->foto_barang_path)) {
            Storage::disk('public')->delete($aset->foto_barang_path);
        }

        $aset->delete();

        return redirect()->route('admin.sarpras.aset.index')
            ->with('status', 'Aset barang berhasil dihapus.');
    }
}
