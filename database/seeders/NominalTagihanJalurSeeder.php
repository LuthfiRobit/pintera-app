<?php
// database/seeders/NominalTagihanJalurSeeder.php

namespace Database\Seeders;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class NominalTagihanJalurSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        // Prestasi sengaja TIDAK diberi nominal apapun (baik SMP maupun SMA) -- demonstrasi
        // yang sudah mapan bahwa TagihanGenerator melewati kombinasi jenis-tagihan x jalur yang
        // belum dikonfigurasi, tidak pernah membuat tagihan Rp0 palsu untuk itu.
        $this->seedNominal($smp, ['Reguler' => 150000, 'Afirmasi' => 0], 'pendaftaran');
        $this->seedNominal($smp, ['Reguler' => 3000000, 'Afirmasi' => 0], 'daftar_ulang');

        $this->seedNominal($sma, ['Reguler' => 200000, 'Afirmasi' => 0], 'pendaftaran');
        $this->seedNominal($sma, ['Reguler' => 4500000, 'Afirmasi' => 0], 'daftar_ulang');
    }

    /**
     * @param  array<string, int>  $nominalPerJalur
     */
    private function seedNominal(Lembaga $lembaga, array $nominalPerJalur, string $kategori): void
    {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

        if (! $aktif) {
            return;
        }

        $jenisTagihanNama = $kategori === 'pendaftaran' ? 'Biaya Pendaftaran' : 'Uang Pangkal';
        $jenisTagihan = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', $jenisTagihanNama)->first();

        if (! $jenisTagihan) {
            return;
        }

        foreach ($nominalPerJalur as $namaJalur => $nominal) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            NominalTagihanJalur::firstOrCreate(
                ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id],
                ['nominal' => $nominal]
            );
        }
    }
}
