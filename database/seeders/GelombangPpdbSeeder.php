<?php
// database/seeders/GelombangPpdbSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class GelombangPpdbSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                if ($tahunAjaran->status_aktif) {
                    $this->seedGelombang($lembaga, $tahunAjaran, [
                        ['nama' => 'Gelombang 1', 'tanggal_buka' => now()->subDays(5)->toDateString(), 'tanggal_tutup' => now()->addMonths(2)->toDateString(), 'kuota' => 80],
                        ['nama' => 'Gelombang 2', 'tanggal_buka' => now()->addMonths(3)->toDateString(), 'tanggal_tutup' => now()->addMonths(4)->toDateString(), 'kuota' => 40],
                    ]);
                } else {
                    $this->seedGelombang($lembaga, $tahunAjaran, [
                        ['nama' => 'Gelombang 1', 'tanggal_buka' => '2025-01-06', 'tanggal_tutup' => '2025-02-14', 'kuota' => 80],
                        ['nama' => 'Gelombang 2', 'tanggal_buka' => '2025-03-03', 'tanggal_tutup' => '2025-04-11', 'kuota' => 40],
                    ]);
                }
            }
        }
    }

    private function seedGelombang(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $gelombangConfig): void
    {
        foreach ($gelombangConfig as $g) {
            GelombangPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => $g['nama']],
                [
                    'tanggal_buka' => $g['tanggal_buka'],
                    'tanggal_tutup' => $g['tanggal_tutup'],
                    'kuota' => $g['kuota'],
                ]
            );
        }
    }
}
