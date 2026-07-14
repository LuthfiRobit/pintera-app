<?php

namespace Database\Factories;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeleksiPpdbFactory extends Factory
{
    protected $model = SeleksiPpdb::class;

    public function definition(): array
    {
        return [
            'jalur_ppdb_id' => JalurPpdb::factory(),
            'gelombang_ppdb_id' => GelombangPpdb::factory(),
            'jenis_tes_master_id' => JenisTesMaster::factory(),
            'jadwal' => now()->addWeek(),
            'kriteria_kelulusan' => 'Nilai minimal 65',
            'bobot' => 50,
        ];
    }
}
