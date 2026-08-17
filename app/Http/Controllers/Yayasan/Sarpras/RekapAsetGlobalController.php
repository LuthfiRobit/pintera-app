<?php

namespace App\Http\Controllers\Yayasan\Sarpras;

use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RekapAsetGlobalController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('sarpras.aset.view');

        $yayasanId = $this->tenantContext->activeYayasanId() ?? \App\Models\Yayasan::first()?->id;

        $lembagaList = $yayasanId ? Lembaga::where('yayasan_id', $yayasanId)->get() : Lembaga::all();

        $totalGedung = $yayasanId ? Gedung::withoutGlobalScope(TenantScope::class)->where('yayasan_id', $yayasanId)->count() : Gedung::withoutGlobalScope(TenantScope::class)->count();
        $totalRuangan = $yayasanId ? Ruangan::withoutGlobalScope(TenantScope::class)->where('yayasan_id', $yayasanId)->count() : Ruangan::withoutGlobalScope(TenantScope::class)->count();
        $totalAset = $yayasanId ? AsetBarang::withoutGlobalScope(TenantScope::class)->where('yayasan_id', $yayasanId)->sum('qty') : AsetBarang::withoutGlobalScope(TenantScope::class)->sum('qty');
        $totalNilaiAset = $yayasanId ? AsetBarang::withoutGlobalScope(TenantScope::class)->where('yayasan_id', $yayasanId)->sum('harga_perolehan') : AsetBarang::withoutGlobalScope(TenantScope::class)->sum('harga_perolehan');

        $rekapPerLembaga = ($yayasanId ? Lembaga::where('yayasan_id', $yayasanId) : Lembaga::query())
            ->withCount(['gedung' => fn ($q) => $q->withoutGlobalScope(TenantScope::class), 'ruangan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->get()
            ->map(function ($lem) {
                $lem->total_aset_qty = AsetBarang::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lem->id)->sum('qty');
                $lem->total_nilai_aset = AsetBarang::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lem->id)->sum('harga_perolehan');

                return $lem;
            });

        return view('portals.yayasan.sarpras.rekap', compact(
            'lembagaList',
            'totalGedung',
            'totalRuangan',
            'totalAset',
            'totalNilaiAset',
            'rekapPerLembaga'
        ));
    }
}
