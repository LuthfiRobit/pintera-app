<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Sarpras\DataTransferObjects\KategoriAsetData;
use App\Domains\Sarpras\Models\KategoriAset;
use Illuminate\Support\Facades\DB;

final class CreateKategoriAsetAction
{
    public function execute(KategoriAsetData $data): KategoriAset
    {
        return DB::transaction(function () use ($data) {
            return KategoriAset::create([
                'yayasan_id' => $data->yayasanId,
                'lembaga_id' => $data->lembagaId,
                'kode_kategori' => $data->kodeKategori,
                'nama_kategori' => $data->namaKategori,
                'deskripsi' => $data->deskripsi,
            ]);
        });
    }
}
