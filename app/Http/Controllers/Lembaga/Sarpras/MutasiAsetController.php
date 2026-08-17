<?php

namespace App\Http\Controllers\Lembaga\Sarpras;

use App\Domains\Sarpras\Actions\MutasiAsetRuanganAction;
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

        $query = RiwayatMutasiAset::query()
            ->with(['asetBarang', 'ruanganAsal', 'ruanganTujuan', 'dilakukanOleh'])
            ->whereHas('asetBarang', function ($q) use ($lembagaId, $yayasanId) {
                if ($lembagaId) {
                    $q->where('lembaga_id', $lembagaId);
                } elseif ($yayasanId) {
                    $q->where('yayasan_id', $yayasanId);
                }
            });

        $totalMutasi = (clone $query)->count();
        $mutasiBulanIni = (clone $query)->whereMonth('tanggal_mutasi', now()->month)->whereYear('tanggal_mutasi', now()->year)->count();

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('alasan_mutasi', 'like', "%{$search}%")
                    ->orWhereHas('asetBarang', fn ($q2) => $q2->where('nama_barang', 'like', "%{$search}%")->orWhere('kode_inventaris', 'like', "%{$search}%"))
                    ->orWhereHas('ruanganAsal', fn ($q2) => $q2->where('nama_ruangan', 'like', "%{$search}%"))
                    ->orWhereHas('ruanganTujuan', fn ($q2) => $q2->where('nama_ruangan', 'like', "%{$search}%"));
            });
        }

        $perPage = $request->integer('per_page', 20);
        $mutasiList = $query->latest('tanggal_mutasi')->paginate($perPage)->withQueryString();

        if ($request->ajax()) {
            return view('portals.lembaga.sarpras.aset._daftar-mutasi', compact('mutasiList'));
        }

        return view('portals.lembaga.sarpras.aset.mutasi-index', compact('mutasiList', 'totalMutasi', 'mutasiBulanIni', 'perPage'));
    }

    public function store(StoreMutasiAsetRequest $request): RedirectResponse
    {
        try {
            $dto = $request->toDTO($request->user()->id);
            $this->mutasiAction->execute($dto);

            return back()->with('success', 'Mutasi lokasi aset berhasil dicatat.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
