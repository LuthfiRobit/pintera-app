<?php
// database/seeders/PendaftaranSeeder.php

namespace Database\Seeders;

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class PendaftaranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
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

            $staf = User::where('lembaga_id', $lembaga->id)->first();

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Menunggu Verifikasi', 'wali.menunggu@example.test', [
                'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
                'submitted_at' => now()->subDays(random_int(1, 5)),
            ]);

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Diterima', 'wali.diterima@example.test', [
                'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
                'submitted_at' => now()->subDays(random_int(1, 5)),
                'status' => 'diterima',
                'catatan_keputusan' => 'Nilai dan kelengkapan dokumen memenuhi syarat.',
                'ditetapkan_oleh_user_id' => $staf?->id,
                'ditetapkan_pada' => now(),
            ]);

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Ditolak', 'wali.ditolak@example.test', [
                'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
                'submitted_at' => now()->subDays(random_int(1, 5)),
                'status' => 'ditolak',
                'catatan_keputusan' => 'Nilai belum memenuhi kriteria kelulusan minimum.',
                'ditetapkan_oleh_user_id' => $staf?->id,
                'ditetapkan_pada' => now(),
            ]);

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Cicilan Demo', 'wali.cicilan-demo@example.test', [
                'kode_pendaftaran' => 'REG-PEMBAYARAN-DEMO-'.$lembaga->id,
                'submitted_at' => now()->subDays(2),
                'status' => 'diterima',
                'ditetapkan_pada' => now()->subDay(),
            ]);
        }
    }

    private function seedPendaftaran(
        Lembaga $lembaga,
        TahunAjaran $tahunAjaran,
        JalurPpdb $jalur,
        GelombangPpdb $gelombang,
        string $namaCalon,
        string $email,
        array $extra
    ): void {
        $calonMurid = CalonMurid::where('nama_lengkap', $namaCalon.' ('.$lembaga->nama.')')->first();

        if (! $calonMurid) {
            return;
        }

        Pendaftaran::firstOrCreate(
            ['email_pendaftaran' => $email, 'lembaga_id' => $lembaga->id],
            array_merge([
                'calon_murid_id' => $calonMurid->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'jalur_ppdb_id' => $jalur->id,
                'gelombang_ppdb_id' => $gelombang->id,
            ], $extra)
        );
    }
}
