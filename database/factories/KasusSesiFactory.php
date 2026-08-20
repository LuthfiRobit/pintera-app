<?php
// database/factories/KasusSesiFactory.php

namespace Database\Factories;

use App\Enums\StatusKasusSesi;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use Illuminate\Database\Eloquent\Factories\Factory;

class KasusSesiFactory extends Factory
{
    protected $model = KasusSesi::class;

    public function definition(): array
    {
        return [
            'kasus_id' => Kasus::factory(),
            'dijadwalkan_pada' => now()->addDays(3),
            'peserta' => 'siswa',
            'lokasi_mode' => 'Ruang BK',
            'status' => StatusKasusSesi::Terjadwal,
        ];
    }
}
