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
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $smpLama = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', false)->firstOrFail();
        $smpBaru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->firstOrFail();
        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();

        // Tahun ajaran lama SMP: tanggal tetap (2025), sengaja hanya untuk uji fitur duplikasi,
        // bukan untuk wizard SPMB publik sungguhan.
        $this->seedGelombang($smp, $smpLama, [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => '2025-01-06', 'tanggal_tutup' => '2025-02-14', 'kuota' => 80],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => '2025-03-03', 'tanggal_tutup' => '2025-04-11', 'kuota' => 40],
        ]);

        // Tahun ajaran aktif SMP dan SMA: tanggal relatif ke hari ini, supaya selalu "sedang buka"
        // kapan pun seeder dijalankan -- wizard SPMB publik langsung bisa diuji.
        $this->seedGelombang($smp, $smpBaru, [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => now()->subDays(5)->toDateString(), 'tanggal_tutup' => now()->addMonths(2)->toDateString(), 'kuota' => 80],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => now()->addMonths(3)->toDateString(), 'tanggal_tutup' => now()->addMonths(4)->toDateString(), 'kuota' => 40],
        ]);

        $this->seedGelombang($sma, $smaBaru, [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => now()->subDays(5)->toDateString(), 'tanggal_tutup' => now()->addMonths(2)->toDateString(), 'kuota' => 120],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => now()->addMonths(3)->toDateString(), 'tanggal_tutup' => now()->addMonths(4)->toDateString(), 'kuota' => 60],
        ]);
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
