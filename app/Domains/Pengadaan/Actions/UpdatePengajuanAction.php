<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaanItem;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePengajuanAction
{
    public function execute(PengajuanPengadaan $proposal, PengajuanPengadaanData $data): PengajuanPengadaan
    {
        if (! in_array($proposal->status, [StatusPengajuan::Draft, StatusPengajuan::RevisionRequired])) {
            throw ValidationException::withMessages([
                'status' => 'Proposal hanya dapat diedit saat berstatus Draft atau Revisi Dibutuhkan.',
            ]);
        }

        return DB::transaction(function () use ($proposal, $data) {
            $totalEstimasi = 0;
            foreach ($data->items as $item) {
                $qty = (int) ($item['qty'] ?? 1);
                $harga = (float) ($item['estimasi_harga_satuan'] ?? 0);
                $totalEstimasi += ($qty * $harga);
            }

            $proposal->update([
                'judul_pengajuan' => $data->judulPengajuan,
                'latar_belakang' => $data->latarBelakang,
                'tingkat_urgensi' => $data->tingkatUrgensi,
                'total_estimasi' => $totalEstimasi,
            ]);

            // Sync items: keep track of processed item IDs
            $existingItemIds = $proposal->items()->pluck('id')->toArray();
            $processedItemIds = [];

            foreach ($data->items as $itemData) {
                $qty = (int) ($itemData['qty'] ?? 1);
                $harga = (float) ($itemData['estimasi_harga_satuan'] ?? 0);
                $total = $qty * $harga;
                $tipePencatatan = isset($itemData['tipe_pencatatan']) && is_string($itemData['tipe_pencatatan'])
                    ? TipePencatatanAset::from($itemData['tipe_pencatatan'])
                    : ($itemData['tipe_pencatatan'] ?? TipePencatatanAset::Unit);

                $payload = [
                    'kategori_aset_id' => $itemData['kategori_aset_id'] ?? null,
                    'target_ruangan_id' => $itemData['target_ruangan_id'] ?? null,
                    'nama_barang' => $itemData['nama_barang'],
                    'merk' => $itemData['merk'] ?? null,
                    'spesifikasi' => $itemData['spesifikasi'] ?? null,
                    'qty' => $qty,
                    'satuan' => $itemData['satuan'] ?? 'unit',
                    'estimasi_harga_satuan' => $harga,
                    'total_estimasi' => $total,
                    'tipe_pencatatan' => $tipePencatatan,
                    'status_item' => StatusItemPengajuan::Pending,
                ];

                if (isset($itemData['foto_referensi_path'])) {
                    $payload['foto_referensi_path'] = $itemData['foto_referensi_path'];
                }

                if (! empty($itemData['id']) && in_array($itemData['id'], $existingItemIds)) {
                    $item = PengajuanPengadaanItem::find($itemData['id']);
                    if ($item && $item->status_item === StatusItemPengajuan::Approved) {
                        $payload['status_item'] = StatusItemPengajuan::Approved;
                    } else {
                        $payload['status_item'] = StatusItemPengajuan::Pending;
                    }
                    $item->update($payload);
                    $processedItemIds[] = $item->id;
                } else {
                    $payload['status_item'] = StatusItemPengajuan::Pending;
                    $newItem = $proposal->items()->create($payload);
                    $processedItemIds[] = $newItem->id;
                }
            }

            // Remove deleted items
            $deletedIds = array_diff($existingItemIds, $processedItemIds);
            if (! empty($deletedIds)) {
                PengajuanPengadaanItem::whereIn('id', $deletedIds)->delete();
            }

            return $proposal->load('items');
        });
    }
}
