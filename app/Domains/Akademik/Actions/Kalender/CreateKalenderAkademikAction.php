<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;
use Illuminate\Validation\ValidationException;

final class CreateKalenderAkademikAction
{
    public function execute(KalenderAkademikData $data, ?int $lembagaId): KalenderAkademik
    {
        $tanggalSelesai = $data->tanggalSelesai ?? $data->tanggal;

        if ($this->tumpangTindih($lembagaId, $data->tanggal, $tanggalSelesai)) {
            throw ValidationException::withMessages([
                'tanggal' => 'Rentang tanggal ini tumpang tindih dengan entri lain pada cakupan yang sama.',
            ]);
        }

        return KalenderAkademik::create([
            'lembaga_id' => $lembagaId,
            'tanggal' => $data->tanggal,
            'tanggal_selesai' => $tanggalSelesai,
            'nama' => $data->nama,
            'tipe' => $data->tipe,
            'keterangan' => $data->keterangan,
        ]);
    }

    /**
     * Detects whether [$mulai, $selesai] overlaps an existing entry in the
     * same scope (same lembaga_id, or both national when $lembagaId is
     * null). Mirrors KalenderAkademikResolver::cocokRentang's handling of a
     * null tanggal_selesai: such a row is a single-day entry whose
     * *effective* end date is its own `tanggal`, not an open-ended/unbounded
     * range. Treating "tanggal_selesai IS NULL" as unconditionally
     * overlapping (i.e. ORing it in without also checking the existing
     * row's `tanggal` against $mulai) produces false positives for any new
     * range that starts after such a single-day entry.
     */
    private function tumpangTindih(?int $lembagaId, string $mulai, string $selesai, ?int $kecualiId = null): bool
    {
        return KalenderAkademik::where(fn ($q) => $lembagaId === null ? $q->whereNull('lembaga_id') : $q->where('lembaga_id', $lembagaId))
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->where('tanggal', '<=', $selesai)
            ->where(fn ($q) => $q->where('tanggal_selesai', '>=', $mulai)
                ->orWhere(fn ($q2) => $q2->whereNull('tanggal_selesai')->where('tanggal', '>=', $mulai))
            )
            ->exists();
    }
}
