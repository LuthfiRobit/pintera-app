<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
use App\Domains\Akademik\Models\KurikulumAssignment;

class KurikulumAssignmentResolver
{
    /**
     * Precedence (paling spesifik -> paling umum), dinyatakan sbg ORDER BY,
     * pola sama seperti FaseDefaultResolver::resolve(). tahun_ajaran_id
     * adalah filter EKSAK, bukan bagian precedence -- tidak ada fallback
     * lintas tahun ajaran.
     *
     * @throws KurikulumAssignmentNotFoundException kalau tidak ada assignment yang cocok sama sekali.
     */
    public function resolve(int $tahunAjaranId, string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): KurikulumFramework
    {
        $query = KurikulumAssignment::where('tahun_ajaran_id', $tahunAjaranId)
            ->where('bentuk_pendidikan', $bentukPendidikan)
            ->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->where(function ($q) use ($tingkat) {
                $q->where('tingkat', $tingkat)->orWhereNull('tingkat');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->orderByRaw('tingkat IS NULL');

        $match = $query->first();

        if ($match === null) {
            throw new KurikulumAssignmentNotFoundException(
                "Kurikulum belum diatur untuk tahun_ajaran_id={$tahunAjaranId}, bentuk_pendidikan={$bentukPendidikan}, tingkat=".($tingkat ?? 'null').'.'
            );
        }

        return $match->kurikulum;
    }
}
