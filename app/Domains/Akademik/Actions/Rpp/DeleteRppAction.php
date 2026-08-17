<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rpp;

use App\Domains\Akademik\Models\Rpp;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteRppAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Rpp $rpp): bool
    {
        if ($rpp->isDisetujui()) {
            throw ValidationException::withMessages([
                'status' => 'Dokumen RPP yang sudah disetujui kurikulum tidak dapat dihapus.',
            ]);
        }

        if ($rpp->file_path && Storage::disk('public')->exists($rpp->file_path)) {
            Storage::disk('public')->delete($rpp->file_path);
        }

        return (bool) $rpp->delete();
    }
}
