<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Support;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;

trait ResolveAnakOrangTuaTrait
{
    /**
     * @return Collection<int, Siswa>
     */
    private function resolveAnakList(User $actor): Collection
    {
        $orangTua = $actor->orangTua;
        if ($orangTua === null) {
            return collect();
        }

        return $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->with('kelas')->get();
    }

    /**
     * @param  Collection<int, Siswa>  $anakList
     */
    private function resolveAnakTerpilih(Collection $anakList, ?int $siswaIdDiminta): ?Siswa
    {
        if ($anakList->isEmpty()) {
            return null;
        }

        if ($siswaIdDiminta !== null) {
            $anak = $anakList->firstWhere('id', $siswaIdDiminta);
            if ($anak !== null) {
                return $anak;
            }
        }

        return $anakList->first();
    }
}
