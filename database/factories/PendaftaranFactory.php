<?php

namespace Database\Factories;

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class PendaftaranFactory extends Factory
{
    protected $model = Pendaftaran::class;

    public function definition(): array
    {
        return [
            'calon_murid_id' => CalonMurid::factory(),
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'jalur_ppdb_id' => JalurPpdb::factory(),
            'gelombang_ppdb_id' => GelombangPpdb::factory(),
            'kode_pendaftaran' => 'REG-'.now()->year.'-'.$this->faker->unique()->numerify('#####'),
            'email_pendaftaran' => $this->faker->safeEmail(),
            'submitted_at' => now(),
        ];
    }
}
