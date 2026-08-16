<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Sarpras\DataTransferObjects\AsetBarangData;
use App\Domains\Sarpras\Models\AsetBarang;
use Illuminate\Support\Facades\DB;

final class UpdateAsetBarangAction
{
    public function execute(AsetBarang $aset, AsetBarangData $data): AsetBarang
    {
        return DB::transaction(function () use ($aset, $data) {
            $payload = [
                'kategori_aset_id' => $data->kategoriAsetId,
                'ruangan_id' => $data->ruanganId,
                'kode_inventaris' => $data->kodeInventaris,
                'nama_barang' => $data->namaBarang,
                'merk' => $data->merk,
                'spesifikasi' => $data->spesifikasi,
                'tipe_pencatatan' => $data->tipePencatatan,
                'qty' => $data->qty,
                'satuan' => $data->satuan,
                'kondisi' => $data->kondisi,
                'sumber_perolehan' => $data->sumberPerolehan,
                'tanggal_perolehan' => $data->tanggalPerolehan,
                'harga_perolehan' => $data->hargaPerolehan,
            ];

            if ($data->fotoBarangPath !== null) {
                $payload['foto_barang_path'] = $data->fotoBarangPath;
            }

            $aset->update($payload);

            return $aset->fresh();
        });
    }
}
