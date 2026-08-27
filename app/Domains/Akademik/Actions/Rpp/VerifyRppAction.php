<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rpp;

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use Illuminate\Validation\ValidationException;

final class VerifyRppAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Rpp $rpp, StatusRpp $targetStatus, int $verifierUserId, int $verifierLembagaId, ?string $catatanRevisi = null): Rpp
    {
        if ((int) $rpp->lembaga_id !== $verifierLembagaId) {
            throw ValidationException::withMessages([
                'lembaga' => 'Anda tidak berwenang memverifikasi dokumen RPP lembaga lain.',
            ]);
        }

        if ($rpp->status !== StatusRpp::Diajukan) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen yang berstatus Menunggu Verifikasi (Diajukan) yang dapat diproses oleh kurikulum.',
            ]);
        }

        if ($targetStatus === StatusRpp::PerluRevisi && empty(trim((string) $catatanRevisi))) {
            throw ValidationException::withMessages([
                'catatan_revisi' => 'Catatan revisi wajib diisi jika meminta perbaikan kepada guru.',
            ]);
        }

        $rpp->update([
            'status' => $targetStatus,
            'catatan_revisi' => $targetStatus === StatusRpp::PerluRevisi ? $catatanRevisi : null,
            'verified_by_user_id' => $verifierUserId,
            'verified_at' => now(),
        ]);

        return $rpp->fresh();
    }
}
