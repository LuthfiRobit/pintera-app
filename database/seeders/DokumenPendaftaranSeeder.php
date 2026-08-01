<?php
// database/seeders/DokumenPendaftaranSeeder.php

namespace Database\Seeders;

use App\Models\DokumenPendaftaran;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DokumenPendaftaranSeeder extends Seeder
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

            if (! $jalur) {
                continue;
            }

            $syaratDokumen = $jalur->dokumenSyarat;

            $this->seedDokumenMenunggu($lembaga, $syaratDokumen, $staf);
            $this->seedDokumenDiterima($lembaga, $syaratDokumen, $staf);
        }
    }

    private function seedDokumenMenunggu(Lembaga $lembaga, Collection $syaratDokumen, ?User $staf): void
    {
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('email_pendaftaran', 'wali.menunggu@example.test')
            ->first();

        if (! $pendaftaran) {
            return;
        }

        foreach ($syaratDokumen as $index => $syarat) {
            $status = match ($index % 3) {
                0 => 'diterima',
                1 => 'ditolak',
                default => 'belum_diverifikasi',
            };

            DokumenPendaftaran::firstOrCreate(
                ['pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id],
                [
                    'file_path' => 'demo/dokumen-contoh.pdf',
                    'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                    'mime_type' => 'application/pdf',
                    'ukuran_bytes' => 102400,
                    'status_verifikasi' => $status,
                    'catatan_verifikasi' => $status === 'ditolak' ? 'Contoh catatan: berkas kurang jelas, mohon diunggah ulang.' : null,
                    'diverifikasi_oleh_user_id' => $status !== 'belum_diverifikasi' ? $staf?->id : null,
                    'diverifikasi_pada' => $status !== 'belum_diverifikasi' ? now() : null,
                ]
            );
        }
    }

    private function seedDokumenDiterima(Lembaga $lembaga, Collection $syaratDokumen, ?User $staf): void
    {
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('email_pendaftaran', 'wali.diterima@example.test')
            ->first();

        if (! $pendaftaran) {
            return;
        }

        foreach ($syaratDokumen as $syarat) {
            DokumenPendaftaran::firstOrCreate(
                ['pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id],
                [
                    'file_path' => 'demo/dokumen-contoh.pdf',
                    'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                    'mime_type' => 'application/pdf',
                    'ukuran_bytes' => 102400,
                    'status_verifikasi' => 'diterima',
                    'diverifikasi_oleh_user_id' => $staf?->id,
                    'diverifikasi_pada' => now(),
                ]
            );
        }
    }
}
