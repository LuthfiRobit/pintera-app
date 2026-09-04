<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;

class FaseDefaultResolver
{
    /**
     * Precedence (paling spesifik -> paling umum), dinyatakan sbg ORDER BY,
     * bukan cabang if/match -- lihat Global Constraints plan ini.
     */
    public function resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase
    {
        $query = FaseDefaultMapping::where('bentuk_pendidikan', $bentukPendidikan)
            ->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->where(function ($q) use ($tingkat) {
                $q->where('tingkat', $tingkat)->orWhereNull('tingkat');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->orderByRaw('tingkat IS NULL');

        $match = $query->first();

        return $match?->fase;
    }
}
