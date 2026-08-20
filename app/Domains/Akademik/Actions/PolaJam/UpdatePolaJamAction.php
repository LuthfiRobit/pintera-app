<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\PolaJamData;
use App\Domains\Akademik\Models\PolaJam;

final class UpdatePolaJamAction
{
    public function execute(PolaJam $polaJam, PolaJamData $data): PolaJam
    {
        $polaJam->update(['nama' => $data->nama]);

        return $polaJam->fresh();
    }
}
