<?php

namespace App\Http\Controllers\Lembaga\Pengadaan;

use App\Domains\Pengadaan\Actions\GenerateInventoryFromLpjAction;
use App\Domains\Pengadaan\Actions\SubmitLpjPengadaanAction;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Shared\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengadaan\ConfirmInventoryConversionRequest;
use App\Http\Requests\Pengadaan\StoreLpjRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LpjPengadaanController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected SubmitLpjPengadaanAction $submitLpjAction,
        protected GenerateInventoryFromLpjAction $generateInventoryAction,
    ) {
    }

    public function create(PengajuanPengadaan $proposal): View
    {
        $this->authorize('pengadaan.lpj.submit');

        if ($proposal->status !== StatusPengajuan::Disbursed) {
            abort(403, 'LPJ hanya dapat diisi untuk proposal yang telah dicairkan dananya.');
        }

        $proposal->load(['items' => fn ($q) => $q->where('status_item', StatusItemPengajuan::Approved)->with(['kategori', 'ruangan']), 'lpj.items']);

        return view('portals.lembaga.pengadaan.lpj.create', compact('proposal'));
    }

    public function store(StoreLpjRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        $items = $request->validated()['items'];
        $processedItems = [];

        foreach ($items as $idx => $item) {
            $fotoNotaPath = null;
            $fotoFisikPath = null;

            if ($request->hasFile("items.{$idx}.foto_nota")) {
                $fotoNotaPath = $request->file("items.{$idx}.foto_nota")->store('pengadaan/nota', 'public');
            }
            if ($request->hasFile("items.{$idx}.foto_fisik")) {
                $fotoFisikPath = $request->file("items.{$idx}.foto_fisik")->store('pengadaan/fisik', 'public');
            }

            $processedItems[] = [
                'pengajuan_item_id' => $item['pengajuan_item_id'],
                'harga_satuan_riil' => $item['harga_satuan_riil'],
                'total_riil' => $item['total_riil'],
                'foto_nota_path' => $fotoNotaPath,
                'foto_fisik_barang_path' => $fotoFisikPath,
            ];
        }

        $buktiKembaliPath = null;
        if ($request->hasFile('bukti_kembali_sisa')) {
            $buktiKembaliPath = $request->file('bukti_kembali_sisa')->store('pengadaan/sisa-kas', 'public');
        }

        $dto = $request->toDTO($buktiKembaliPath, $processedItems);
        $this->submitLpjAction->execute($proposal, $dto);

        return redirect()->route('admin.pengadaan.proposal.show', $proposal)
            ->with('success', 'Laporan Pertanggungjawaban (LPJ) berhasil dikirim untuk diaudit oleh Yayasan.');
    }

    public function stagingInventory(LpjPengadaan $lpj): View
    {
        $this->authorize('pengadaan.lpj.submit');

        if ($lpj->status_lpj !== StatusLpj::Verified) {
            abort(403, 'Inventarisasi hanya dapat dilakukan setelah LPJ diverifikasi.');
        }

        $lpj->load(['proposal.lembaga', 'items.pengajuanItem.kategori', 'items.pengajuanItem.ruangan']);

        return view('portals.lembaga.pengadaan.lpj.staging-inventory', compact('lpj'));
    }

    public function convertInventory(ConfirmInventoryConversionRequest $request, LpjPengadaan $lpj): RedirectResponse
    {
        $serialNumbers = $request->validated()['serial_numbers'] ?? [];
        $createdAssets = $this->generateInventoryAction->execute($lpj, $serialNumbers);

        return redirect()->route('admin.sarpras.aset.index')
            ->with('success', "Berhasil menerbitkan {$createdAssets->count()} item barang inventaris ke Master Sarpras!");
    }
}
