<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\DataTransferObjects\JabatanTambahanMasterData;
use App\Domains\Sdm\Models\JabatanTambahanMaster;

final class CreateJabatanTambahanAction
{
    public function execute(JabatanTambahanMasterData $data, int $yayasanId): JabatanTambahanMaster
    {
        return JabatanTambahanMaster::create([
            'nama' => $data->nama,
            'kelompok' => $data->kelompok,
            'yayasan_id' => $yayasanId,
        ])->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]);
    }
}
