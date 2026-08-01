<?php
// database/seeders/SkemaCicilanSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Services\PembayaranService;
use Illuminate\Database\Seeder;

class SkemaCicilanSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PembayaranService::class);

        foreach (Lembaga::all() as $lembaga) {
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

            if (! $cicilanDemo) {
                continue;
            }

            $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();

            if (! $tagihan || $tagihan->skemaCicilan()->exists()) {
                continue;
            }

            $service->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
        }
    }
}
