<?php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Fase;
use Illuminate\Database\Seeder;

class FaseSeeder extends Seeder
{
    public function run(): void
    {
        $fases = [
            ['kode' => 'foundation', 'nama' => 'Fase Fondasi', 'urutan' => 0],
            ['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1],
            ['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2],
            ['kode' => 'c', 'nama' => 'Fase C', 'urutan' => 3],
            ['kode' => 'd', 'nama' => 'Fase D', 'urutan' => 4],
            ['kode' => 'e', 'nama' => 'Fase E', 'urutan' => 5],
            ['kode' => 'f', 'nama' => 'Fase F', 'urutan' => 6],
        ];

        foreach ($fases as $fase) {
            Fase::updateOrCreate(['kode' => $fase['kode']], $fase);
        }
    }
}
