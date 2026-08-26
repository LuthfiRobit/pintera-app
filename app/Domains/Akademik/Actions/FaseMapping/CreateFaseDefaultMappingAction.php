<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\FaseMapping;

use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\FaseDefaultMapping;

final class CreateFaseDefaultMappingAction
{
    public function execute(FaseDefaultMappingData $data): FaseDefaultMapping
    {
        return FaseDefaultMapping::create([
            'lembaga_id' => $data->lembagaId,
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
        ]);
    }
}
