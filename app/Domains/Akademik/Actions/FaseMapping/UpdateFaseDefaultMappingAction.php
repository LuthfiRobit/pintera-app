<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\FaseMapping;

use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\FaseDefaultMapping;

final class UpdateFaseDefaultMappingAction
{
    public function execute(FaseDefaultMapping $mapping, FaseDefaultMappingData $data): FaseDefaultMapping
    {
        $mapping->update([
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
        ]);

        return $mapping;
    }
}
