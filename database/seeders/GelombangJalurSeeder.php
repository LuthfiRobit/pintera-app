<?php
// database/seeders/GelombangJalurSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class GelombangJalurSeeder extends Seeder
{
    public function run(): void
    {
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();
        $sditAktif = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->firstOrFail();

        $gelombang1 = GelombangPpdb::where('lembaga_id', $sdit->id)
            ->where('tahun_ajaran_id', $sditAktif->id)
            ->where('nama', 'Gelombang 1')
            ->firstOrFail();

        $jalurDiizinkan = JalurPpdb::where('lembaga_id', $sdit->id)
            ->where('tahun_ajaran_id', $sditAktif->id)
            ->whereIn('nama', ['Reguler', 'Prestasi'])
            ->pluck('id');

        $gelombang1->jalur()->sync($jalurDiizinkan);
    }
}
