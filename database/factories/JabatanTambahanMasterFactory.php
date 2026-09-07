<?php

namespace Database\Factories;

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class JabatanTambahanMasterFactory extends Factory
{
    protected $model = JabatanTambahanMaster::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'nama' => $this->faker->unique()->jobTitle(),
            'kelompok' => $this->faker->randomElement(['struktural', 'fungsional']),
        ];
    }
}
