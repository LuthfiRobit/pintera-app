<?php

namespace App\Http\Controllers\Lembaga\Sarpras;

use App\Domains\Sarpras\Actions\MutasiAsetRuanganAction;
use App\Domains\Sarpras\DataTransferObjects\MutasiAsetData;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\RiwayatMutasiAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sarpras\StoreMutasiAsetRequest;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MutasiAsetController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected MutasiAsetRuanganAction $mutasiAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('sarpras.mutasi.view');

        $lembagaId = $this->tenantContext->activeLembagaId();
        $yayasanId = $this->tenantContext->activeYayasanId();

        $mutasiList = RiwayatMutasiAset::query()
            ->with(['asetBarang', 'ruanganAsal', 'ruanganTujuan', 'dilakukanOleh'])
            ->whereHas('asetBarang', function ($query) use ($lembagaId, $yayasanId) {
                if ($lembagaId) {
                    $query->where('lembaga_id', $lembagaId);
                } elseif ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
            })
            ->latest('tanggal_mutasi')
            ->paginate(15);

        return view('portals.lembaga.sarpras.aset.mutasi-index', compact('mutasiList'));
    }

    public function store(StoreMutasiAsetRequest $request): RedirectResponse
    {
        try {
            $dto = MutasiAsetData::fromArray($request->validated(), $request->user()->id);
            $this->mutasiAction->execute($dto);

            return back()->with('success', 'Mutasi lokasi aset berhasil dicatat.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
