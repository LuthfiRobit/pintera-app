<?php

namespace App\Domains\Keuangan\Actions;

use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteJenisTagihanAction
{
    /**
     * @throws ValidationException Jika jenis tagihan sudah memiliki relasi tagihan_item
     */
    public function execute(JenisTagihan $jenisTagihan): void
    {
        if ($jenisTagihan->tagihanItem()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Jenis tagihan tidak dapat dihapus karena sudah memiliki tagihan yang diterbitkan.',
            ]);
        }

        DB::transaction(function () use ($jenisTagihan) {
            $jenisTagihan->nominalJalur()->delete();
            $jenisTagihan->delete();
        });
    }
}
