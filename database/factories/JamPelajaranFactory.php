<?php

namespace Database\Factories;

use App\Enums\Hari;
use App\Models\JamPelajaran;
use App\Models\PolaJam;
use Illuminate\Database\Eloquent\Factories\Factory;

class JamPelajaranFactory extends Factory
{
    protected $model = JamPelajaran::class;

    public function definition(): array
    {
        return [
            'pola_jam_id' => PolaJam::factory(),
            'hari' => Hari::Senin->value,
            'urutan' => 1,
            'label' => 'Jam ke-1',
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:35',
            'is_pelajaran' => true,
        ];
    }
}
