<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Lembaga;
use Illuminate\Validation\ValidationException;

final class DeleteJabatanTambahanAction
{
    public function execute(JabatanTambahanMaster $jabatanTambahanMaster): void
    {
        $lembagaIdsYayasan = Lembaga::where('yayasan_id', $jabatanTambahanMaster->yayasan_id)->pluck('id');

        $guruCount = $jabatanTambahanMaster->guru()
            ->withoutGlobalScopes()
            ->whereIn('guru.lembaga_id', $lembagaIdsYayasan)
            ->count();

        if ($guruCount > 0) {
            throw ValidationException::withMessages([
                'jabatan' => "Jabatan tidak dapat dihapus karena saat ini masih disandang oleh {$guruCount} Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.",
            ]);
        }

        $jabatanTambahanMaster->delete();
    }
}
