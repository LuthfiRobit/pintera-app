<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\LayananKhususLembaga;
use Illuminate\Database\Seeder;

class LayananKhususLembagaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            LayananKhususLembaga::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'jenis_layanan' => 'Kelas Tahfidz Intensif'],
                [
                    'no_sk' => 'SK.021/Yayasan/2020',
                    'tmt' => '2020-07-01',
                    'tst' => null,
                    'keterangan' => 'Program unggulan hafalan Al-Qur\'an minimal 5 juz sebelum lulus.',
                ]
            );
        }
    }
}
