<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Sarpras\DataTransferObjects\GedungData;
use App\Domains\Sarpras\Models\Gedung;
use Illuminate\Support\Facades\DB;

final class CreateGedungAction
{
    public function execute(GedungData $data): Gedung
    {
        return DB::transaction(function () use ($data) {
            return Gedung::create([
                'yayasan_id' => $data->yayasanId,
                'lembaga_id' => $data->lembagaId,
                'kode_gedung' => $data->kodeGedung,
                'nama_gedung' => $data->namaGedung,
                'jumlah_lantai' => $data->jumlahLantai,
                'deskripsi' => $data->deskripsi,
                'is_aktif' => $data->isAktif,
            ]);
        });
    }
}
