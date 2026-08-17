<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rpp;

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use Illuminate\Validation\ValidationException;

final class SubmitRppAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Rpp $rpp): Rpp
    {
        if (! $rpp->canBeEditedByGuru()) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen berstatus Draft atau Perlu Revisi yang dapat diajukan ke kurikulum.',
            ]);
        }

        $rpp->update([
            'status' => StatusRpp::Diajukan,
        ]);

        return $rpp->fresh();
    }
}
