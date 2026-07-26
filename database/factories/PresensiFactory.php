<?php

namespace Database\Factories;

use App\Models\Presensi;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class PresensiFactory extends Factory
{
    protected $model = Presensi::class;

    public function definition(): array
    {
        return [
            'sesi_pembelajaran_id' => SesiPembelajaran::factory(),
            'siswa_id' => Siswa::factory(),
            'status' => 'hadir',
            'keterangan' => null,
        ];
    }
}
