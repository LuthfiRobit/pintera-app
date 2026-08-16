<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\DataTransferObjects\LpjPengadaanData;
use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Pengadaan\Models\LpjPengadaanItem;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitLpjPengadaanAction
{
    public function execute(PengajuanPengadaan $proposal, LpjPengadaanData $data): LpjPengadaan
    {
        if ($proposal->status !== StatusPengajuan::Disbursed) {
            throw ValidationException::withMessages([
                'lpj' => 'LPJ hanya dapat diajukan setelah dana proposal dicairkan (Disbursed).',
            ]);
        }

        return DB::transaction(function () use ($proposal, $data) {
            $totalRealisasi = 0;
            foreach ($data->items as $item) {
                $totalRealisasi += (float) ($item['total_riil'] ?? 0);
            }

            $nominalCair = (float) $proposal->nominal_pencairan;
            $selisih = $nominalCair - $totalRealisasi;

            $lpj = LpjPengadaan::updateOrCreate(
                ['pengajuan_pengadaan_id' => $proposal->id],
                [
                    'total_realisasi' => $totalRealisasi,
                    'selisih_dana' => $selisih,
                    'bukti_kembali_sisa_dana_path' => $data->buktiKembaliSisaDanaPath,
                    'status_lpj' => StatusLpj::Submitted,
                ]
            );

            foreach ($data->items as $itemData) {
                LpjPengadaanItem::updateOrCreate(
                    [
                        'lpj_pengadaan_id' => $lpj->id,
                        'pengajuan_item_id' => $itemData['pengajuan_item_id'],
                    ],
                    [
                        'harga_satuan_riil' => (float) ($itemData['harga_satuan_riil'] ?? 0),
                        'total_riil' => (float) ($itemData['total_riil'] ?? 0),
                        'foto_nota_path' => $itemData['foto_nota_path'] ?? null,
                        'foto_fisik_barang_path' => $itemData['foto_fisik_barang_path'] ?? null,
                        'status_konversi_sarpras' => 'pending',
                    ]
                );
            }

            return $lpj->load('items');
        });
    }
}
