<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use Illuminate\Validation\ValidationException;

final class DeleteJenisKaryawanAction
{
    public function execute(JenisKaryawanMaster $jenisKaryawanMaster): void
    {
        $karyawanCount = $jenisKaryawanMaster->karyawan()->count();

        if ($karyawanCount > 0) {
            throw ValidationException::withMessages([
                'jenis_karyawan' => "Jenis karyawan tidak dapat dihapus karena masih dipakai oleh {$karyawanCount} karyawan.",
            ]);
        }

        $jenisKaryawanMaster->delete();
    }
}
