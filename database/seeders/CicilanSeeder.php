<?php
// database/seeders/CicilanSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use Illuminate\Database\Seeder;
use RuntimeException;

class CicilanSeeder extends Seeder
{
    /**
     * SkemaCicilanSeeder already creates every Cicilan row as a side effect of
     * PembayaranService::buatSkemaCicilan() (one atomic transaction covering both
     * tables). This seeder exists to give the `cicilan` table its own explicit,
     * documented owner in DatabaseSeeder's call chain, and to fail loudly if that
     * invariant is ever broken (e.g. someone reorders DatabaseSeeder and puts this
     * before SkemaCicilanSeeder).
     */
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

            if (! $cicilanDemo) {
                continue;
            }

            $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();

            if (! $tagihan) {
                continue;
            }

            $skema = $tagihan->skemaCicilan;

            if (! $skema || $skema->cicilan()->count() !== 3) {
                throw new RuntimeException(
                    "CicilanSeeder: skema cicilan untuk tagihan #{$tagihan->id} (lembaga {$lembaga->nama}) belum lengkap -- pastikan SkemaCicilanSeeder berjalan sebelum CicilanSeeder di DatabaseSeeder."
                );
            }
        }
    }
}
