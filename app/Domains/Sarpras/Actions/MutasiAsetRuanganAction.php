<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Sarpras\DataTransferObjects\MutasiAsetData;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\RiwayatMutasiAset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MutasiAsetRuanganAction
{
    public function execute(MutasiAsetData $data): RiwayatMutasiAset
    {
        return DB::transaction(function () use ($data) {
            $aset = AsetBarang::findOrFail($data->asetBarangId);
            $ruanganAsalId = $aset->ruangan_id;

            if ($ruanganAsalId === $data->ruanganTujuanId) {
                throw new InvalidArgumentException('Ruangan tujuan mutasi tidak boleh sama dengan ruangan asal.');
            }

            if ($data->qtyPindah <= 0 || $data->qtyPindah > $aset->qty) {
                throw new InvalidArgumentException("Jumlah yang dimutasi ({$data->qtyPindah}) tidak valid. Stok tersedia: {$aset->qty}.");
            }

            // If whole unit or all batch qty moved:
            if ($data->qtyPindah === $aset->qty || $aset->tipe_pencatatan === TipePencatatanAset::Unit) {
                $aset->update([
                    'ruangan_id' => $data->ruanganTujuanId,
                ]);
            } else {
                // Partial batch split:
                $aset->decrement('qty', $data->qtyPindah);

                // Check if identical batch item already exists in target room
                $targetAset = AsetBarang::where('ruangan_id', $data->ruanganTujuanId)
                    ->where('nama_barang', $aset->nama_barang)
                    ->where('kategori_aset_id', $aset->kategori_aset_id)
                    ->where('tipe_pencatatan', TipePencatatanAset::Batch)
                    ->where('kondisi', $aset->kondisi)
                    ->first();

                if ($targetAset) {
                    $targetAset->increment('qty', $data->qtyPindah);
                } else {
                    $newAset = $aset->replicate();
                    $newAset->ruangan_id = $data->ruanganTujuanId;
                    $newAset->qty = $data->qtyPindah;
                    $newAset->kode_inventaris = $aset->kode_inventaris . '-M' . time();
                    $newAset->save();
                }
            }

            return RiwayatMutasiAset::create([
                'aset_barang_id' => $aset->id,
                'ruangan_asal_id' => $ruanganAsalId,
                'ruangan_tujuan_id' => $data->ruanganTujuanId,
                'qty_pindah' => $data->qtyPindah,
                'tanggal_mutasi' => $data->tanggalMutasi,
                'alasan_mutasi' => $data->alasanMutasi,
                'dilakukan_oleh_user_id' => $data->dilakukanOlehUserId,
            ]);
        });
    }
}
