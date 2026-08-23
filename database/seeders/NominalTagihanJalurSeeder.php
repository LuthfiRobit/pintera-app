<?php
// database/seeders/NominalTagihanJalurSeeder.php

namespace Database\Seeders;

use App\Models\JalurPpdb;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class NominalTagihanJalurSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $nominalPendaftaran = match ($lembaga->bentuk_pendidikan) {
                'KB', 'TK' => 100000,
                'SD' => 125000,
                default => 150000,
            };

            $nominalDaftarUlang = match ($lembaga->bentuk_pendidikan) {
                'KB' => 1500000,
                'TK' => 1800000,
                'SD' => 2500000,
                default => 3000000,
            };

            // Prestasi sengaja TIDAK diberi nominal apapun -- demonstrasi yang sudah mapan
            // bahwa TagihanGenerator melewati kombinasi jenis-tagihan x jalur yang belum
            // dikonfigurasi, tidak pernah membuat tagihan Rp0 palsu untuk itu.
            $this->seedNominal($lembaga, ['Reguler' => $nominalPendaftaran, 'Afirmasi' => 0], 'pendaftaran');
            $this->seedNominal($lembaga, ['Reguler' => $nominalDaftarUlang, 'Afirmasi' => 0], 'daftar_ulang');
        }
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
