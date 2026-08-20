<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\PolaJamData;
use App\Domains\Akademik\Models\PolaJam;

final class CreatePolaJamAction
{
    public function execute(PolaJamData $data): PolaJam
    {
        return PolaJam::create([
            'nama' => $data->nama,
            'lembaga_id' => $data->lembagaId,
        ]);
    }
}
