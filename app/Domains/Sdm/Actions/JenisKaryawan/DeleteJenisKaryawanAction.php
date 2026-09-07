<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Scopes\TenantScope;
use Illuminate\Validation\ValidationException;

final class DeleteJenisKaryawanAction
{
    public function execute(JenisKaryawanMaster $jenisKaryawanMaster): void
    {
        $karyawanCount = Karyawan::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $jenisKaryawanMaster->yayasan_id)
            ->where('jenis_karyawan_id', $jenisKaryawanMaster->id)
            ->count();

        if ($karyawanCount > 0) {
            throw ValidationException::withMessages([
                'jenis_karyawan' => "Jenis karyawan tidak dapat dihapus karena masih dipakai oleh {$karyawanCount} karyawan.",
            ]);
        }

        $jenisKaryawanMaster->delete();
    }
}
