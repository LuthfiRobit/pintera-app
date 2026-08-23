<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\DataTransferObjects\JabatanTambahanMasterData;
use App\Domains\Sdm\Models\JabatanTambahanMaster;

final class UpdateJabatanTambahanAction
{
    public function execute(JabatanTambahanMaster $jabatanTambahanMaster, JabatanTambahanMasterData $data): JabatanTambahanMaster
    {
        $jabatanTambahanMaster->update([
            'nama' => $data->nama,
            'kelompok' => $data->kelompok,
        ]);

        return $jabatanTambahanMaster->fresh(['guru' => fn ($q) => $q->withoutGlobalScopes()])
            ->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]);
    }
}
