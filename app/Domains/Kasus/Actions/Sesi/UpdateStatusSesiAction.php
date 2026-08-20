<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Sesi;

use App\Domains\Kasus\DataTransferObjects\UpdateStatusSesiData;
use App\Domains\Kasus\Models\KasusSesi;

final class UpdateStatusSesiAction
{
    public function execute(KasusSesi $kasusSesi, UpdateStatusSesiData $data): KasusSesi
    {
        $kasusSesi->update([
            'status' => $data->status,
            'catatan_internal' => $data->catatanInternal ?? $kasusSesi->catatan_internal,
            'alasan_batal' => $data->alasanBatal ?? null,
        ]);

        return $kasusSesi->fresh();
    }
}
