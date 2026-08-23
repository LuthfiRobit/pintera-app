<?php

namespace App\Domains\Keuangan\Actions;

use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteJenisTagihanAction
{
    /**
     * @throws ValidationException
     */
    public function execute(JenisTagihan $jenisTagihan): void
    {
        $jumlahTagihan = $jenisTagihan->tagihanItem()->count();
        if ($jumlahTagihan > 0) {
            throw ValidationException::withMessages([
                'jenis_tagihan' => "Tidak bisa dihapus, sudah dipakai di {$jumlahTagihan} tagihan milik calon murid.",
            ]);
        }

        $jumlahNominal = $jenisTagihan->nominalJalur()->count();
        if ($jumlahNominal > 0) {
            throw ValidationException::withMessages([
                'jenis_tagihan' => "Tidak bisa dihapus, sudah ada {$jumlahNominal} nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal.",
            ]);
        }

        DB::transaction(function () use ($jenisTagihan) {
            $jenisTagihan->delete();
        });
    }
}
