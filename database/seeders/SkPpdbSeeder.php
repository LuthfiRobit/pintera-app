<?php
// database/seeders/SkPpdbSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SkPpdbSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $staf = User::where('lembaga_id', $lembaga->id)->first();

            if (! $staf) {
                continue;
            }

            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('tanggal_buka', '<=', now())
                ->where('tanggal_tutup', '>=', now())
                ->first();

            if (! $gelombang) {
                continue;
            }

            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
            $ditolak = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();

            if (! $diterima || ! $ditolak) {
                continue;
            }

            $sk = SkPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nomor_sk' => '421.3/SK-PPDB.DEMO-'.$lembaga->id.'/2026'],
                [
                    'gelombang_ppdb_id' => $gelombang->id,
                    'tanggal_terbit' => now()->toDateString(),
                    'diterbitkan_oleh_user_id' => $staf->id,
                    'file_path' => 'demo/sk-contoh.pdf',
                ]
            );

            Pendaftaran::whereIn('id', [$diterima->id, $ditolak->id])->update(['sk_ppdb_id' => $sk->id]);
        }
    }
}
