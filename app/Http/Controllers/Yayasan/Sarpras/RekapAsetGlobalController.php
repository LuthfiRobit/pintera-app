<?php

namespace App\Http\Controllers\Yayasan\Sarpras;

use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Lembaga;
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

        $totalGedung = $yayasanId ? Gedung::where('yayasan_id', $yayasanId)->count() : Gedung::count();
        $totalRuangan = $yayasanId ? Ruangan::where('yayasan_id', $yayasanId)->count() : Ruangan::count();
        $totalAset = $yayasanId ? AsetBarang::where('yayasan_id', $yayasanId)->sum('qty') : AsetBarang::sum('qty');
        $totalNilaiAset = $yayasanId ? AsetBarang::where('yayasan_id', $yayasanId)->sum('harga_perolehan') : AsetBarang::sum('harga_perolehan');

        $rekapPerLembaga = ($yayasanId ? Lembaga::where('yayasan_id', $yayasanId) : Lembaga::query())
            ->withCount(['gedung', 'ruangan'])
            ->get()
            ->map(function ($lem) {
                $lem->total_aset_qty = AsetBarang::where('lembaga_id', $lem->id)->sum('qty');
                $lem->total_nilai_aset = AsetBarang::where('lembaga_id', $lem->id)->sum('harga_perolehan');

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
