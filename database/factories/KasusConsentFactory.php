<?php

namespace Database\Factories;

use App\Models\Kasus;
use App\Models\KasusConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

class KasusConsentFactory extends Factory
{
    protected $model = KasusConsent::class;

    public function definition(): array
    {
        return [
            'kasus_id' => Kasus::factory(),
            'jenis' => 'sesi_pendampingan',
            'status' => 'menunggu',
        ];
    }
}
