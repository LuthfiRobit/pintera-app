<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Support;

use App\Models\Lembaga;
use App\Models\User;

trait ResolveLembagaScopeTrait
{
    private function resolveLembagaId(User $actor, ?int $lembagaIdDiminta): ?int
    {
        return match ($actor->widestScopeLevel()) {
            'platform' => $lembagaIdDiminta,
            'yayasan' => $this->resolveLembagaIdUntukYayasan($actor),
            default => $actor->lembaga_id,
        };
    }

    private function resolveLembagaIdUntukYayasan(User $actor): int
    {
        $lembagaId = session('active_lembaga_id');
        abort_if($lembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum melakukan aksi ini.');

        $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
        abort_unless($milikYayasan, 422, 'Lembaga aktif di sesi Anda tidak valid untuk yayasan Anda saat ini. Pilih ulang lembaga aktif melalui pengalih lembaga.');

        return $lembagaId;
    }
}
