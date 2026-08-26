<?php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use Illuminate\Database\Seeder;

class FaseDefaultMappingSeeder extends Seeder
{
    public function run(): void
    {
        $faseByKode = Fase::pluck('id', 'kode');

        // Baris di bawah adalah REKOMENDASI PLATFORM SAAT INI (mengikuti Kurikulum
        // Merdeka), bukan kebenaran definisional yang tertanam permanen di kode --
        // bisa diubah lewat UI admin mapping (Task 6) tanpa deployment.
        $mapping = [
            ['bentuk_pendidikan' => 'KB', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'TPA', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'SPS', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'TK', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kode' => 'a'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '2', 'kode' => 'a'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '3', 'kode' => 'b'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '4', 'kode' => 'b'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '5', 'kode' => 'c'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '6', 'kode' => 'c'],
            ['bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'kode' => 'd'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '10', 'kode' => 'e'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '11', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '12', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '10', 'kode' => 'e'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '11', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '12', 'kode' => 'f'],
            // SLB sengaja tidak diberi mapping -- kurikulum SLB punya penyesuaian
            // tersendiri di luar cakupan Sprint 3.
        ];

        foreach ($mapping as $m) {
            FaseDefaultMapping::updateOrCreate(
                ['lembaga_id' => null, 'bentuk_pendidikan' => $m['bentuk_pendidikan'], 'tingkat' => $m['tingkat']],
                ['fase_id' => $faseByKode[$m['kode']]]
            );
        }
    }
}
