<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Sarpras\DataTransferObjects\AsetBarangData;
use App\Domains\Sarpras\Models\AsetBarang;
use Illuminate\Support\Facades\DB;

final class CreateAsetBarangAction
{
    public function execute(AsetBarangData $data): AsetBarang
    {
        return DB::transaction(function () use ($data) {
            return AsetBarang::create([
                'yayasan_id' => $data->yayasanId,
                'lembaga_id' => $data->lembagaId,
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
                'foto_barang_path' => $data->fotoBarangPath,
            ]);
        });
    }
}
