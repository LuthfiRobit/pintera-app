<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class LembagaDataPeriodikSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $aktif) {
                continue;
            }

            foreach ($aktif->semester as $semester) {
                LembagaDataPeriodik::firstOrCreate(
                    ['lembaga_id' => $lembaga->id, 'semester_id' => $semester->id],
                    [
                        'waktu_penyelenggaraan' => 'Pagi',
                        'sumber_listrik' => 'PLN',
                        'daya_listrik' => 5500,
                        'akses_internet' => 'Telkom Indihome (Fiber Optik)',
                        'status_bos' => true,
                        'sertifikasi_iso' => null,
                        'ketersediaan_air_bersih' => true,
                        'kecukupan_air_bersih' => true,
                        'jumlah_tempat_cuci_tangan' => 8,
                        'jumlah_jamban' => 6,
                        'stratifikasi_uks' => 'Strata 3 (Optimal)',
                        'media_kie_sanitasi' => true,
                    ]
                );
            }
        }
    }
}
