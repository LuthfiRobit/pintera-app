<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Sarpras\DataTransferObjects\GedungData;
use App\Domains\Sarpras\Models\Gedung;
use Illuminate\Support\Facades\DB;

final class UpdateGedungAction
{
    public function execute(Gedung $gedung, GedungData $data): Gedung
    {
        return DB::transaction(function () use ($gedung, $data) {
            $gedung->update([
                'kode_gedung' => $data->kodeGedung,
                'nama_gedung' => $data->namaGedung,
                'jumlah_lantai' => $data->jumlahLantai,
                'deskripsi' => $data->deskripsi,
                'is_aktif' => $data->isAktif,
            ]);

            return $gedung->fresh();
        });
    }
}
