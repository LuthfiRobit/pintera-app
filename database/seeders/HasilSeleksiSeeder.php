<?php
// database/seeders/HasilSeleksiSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class HasilSeleksiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $staf = User::where('lembaga_id', $lembaga->id)->first();

            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('nama', 'Reguler')
                ->first();

            $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('tanggal_buka', '<=', now())
                ->where('tanggal_tutup', '>=', now())
                ->first();

            if (! $jalur || ! $gelombang) {
                continue;
            }

            $seleksiList = SeleksiPpdb::where('jalur_ppdb_id', $jalur->id)->where('gelombang_ppdb_id', $gelombang->id)->get();

            $this->seedHasil($lembaga, $seleksiList, 'wali.diterima@demo.test', 75, 95, $staf);
            $this->seedHasil($lembaga, $seleksiList, 'wali.ditolak@demo.test', 30, 55, $staf);
        }
    }

    private function seedHasil(Lembaga $lembaga, Collection $seleksiList, string $email, int $min, int $max, ?User $staf): void
    {
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', $email)->first();

        if (! $pendaftaran) {
            return;
        }

        foreach ($seleksiList as $seleksi) {
            HasilSeleksi::firstOrCreate(
                ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id],
                [
                    'nilai' => random_int($min, $max),
                    'dinilai_oleh_user_id' => $staf?->id,
                    'dinilai_pada' => now(),
                ]
            );
        }
    }
}
