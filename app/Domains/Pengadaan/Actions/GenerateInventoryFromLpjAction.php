<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Sarpras\Actions\CreateAsetBarangAction;
use App\Domains\Sarpras\DataTransferObjects\AsetBarangData;
use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateInventoryFromLpjAction
{
    public function __construct(
        protected CreateAsetBarangAction $createAsetAction,
    ) {
    }

    public function execute(LpjPengadaan $lpj, array $serialNumbers = []): Collection
    {
        if ($lpj->status_lpj !== StatusLpj::Verified) {
            throw ValidationException::withMessages([
                'lpj' => 'Barang inventaris hanya dapat diterbitkan setelah LPJ diverifikasi resmi oleh yayasan.',
            ]);
        }

        $createdAssets = collect();

        DB::transaction(function () use ($lpj, $serialNumbers, &$createdAssets) {
            $proposal = $lpj->proposal;
            $yayasanId = $proposal->yayasan_id;
            $lembagaId = $proposal->lembaga_id;
            $tahunBulan = date('Y/m');

            foreach ($lpj->items as $lpjItem) {
                if ($lpjItem->status_konversi_sarpras === 'converted') {
                    continue;
                }

                $pItem = $lpjItem->pengajuanItem;
                if (! $pItem) {
                    continue;
                }

                $hargaSatuan = $lpjItem->harga_satuan_riil > 0
                    ? $lpjItem->harga_satuan_riil
                    : $pItem->estimasi_harga_satuan;

                if ($pItem->tipe_pencatatan === TipePencatatanAset::Unit) {
                    for ($i = 1; $i <= $pItem->qty; $i++) {
                        $customSn = $serialNumbers[$pItem->id][$i] ?? null;
                        $kodeInventaris = 'INV/' . date('Ymd') . '/' . strtoupper(Str::random(5));

                        $dto = new AsetBarangData(
                            yayasanId: $yayasanId,
                            lembagaId: $lembagaId,
                            kategoriAsetId: $pItem->kategori_aset_id,
                            ruanganId: $pItem->target_ruangan_id,
                            kodeInventaris: $kodeInventaris,
                            namaBarang: $pItem->nama_barang,
                            merk: $pItem->merk,
                            spesifikasi: $pItem->spesifikasi . ($customSn ? "\nS/N: " . $customSn : ''),
                            tipePencatatan: TipePencatatanAset::Unit,
                            qty: 1,
                            satuan: $pItem->satuan ?? 'unit',
                            kondisi: KondisiAset::Baik,
                            sumberPerolehan: SumberPerolehanAset::BeliYayasan,
                            tanggalPerolehan: now()->toDateString(),
                            hargaPerolehan: $hargaSatuan,
                            fotoBarangPath: $lpjItem->foto_fisik_barang_path ?? $pItem->foto_referensi_path,
                        );

                        $asset = $this->createAsetAction->execute($dto);
                        $createdAssets->push($asset);
                    }
                } else {
                    $kodeInventaris = 'INV/' . date('Ymd') . '/' . strtoupper(Str::random(5));
                    $dto = new AsetBarangData(
                        yayasanId: $yayasanId,
                        lembagaId: $lembagaId,
                        kategoriAsetId: $pItem->kategori_aset_id,
                        ruanganId: $pItem->target_ruangan_id,
                        kodeInventaris: $kodeInventaris,
                        namaBarang: $pItem->nama_barang,
                        merk: $pItem->merk,
                        spesifikasi: $pItem->spesifikasi,
                        tipePencatatan: TipePencatatanAset::Batch,
                        qty: $pItem->qty,
                        satuan: $pItem->satuan ?? 'unit',
                        kondisi: KondisiAset::Baik,
                        sumberPerolehan: SumberPerolehanAset::BeliYayasan,
                        tanggalPerolehan: now()->toDateString(),
                        hargaPerolehan: $hargaSatuan * $pItem->qty,
                        fotoBarangPath: $lpjItem->foto_fisik_barang_path ?? $pItem->foto_referensi_path,
                    );

                    $asset = $this->createAsetAction->execute($dto);
                    $createdAssets->push($asset);
                }

                $lpjItem->status_konversi_sarpras = 'converted';
                $lpjItem->save();
            }
        });

        return $createdAssets;
    }
}
