<?php

namespace Database\Factories;

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Enums\Hari;
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
            'label' => fn (array $attributes) => 'Jam ke-'.($attributes['urutan'] ?? 1),
            'jam_mulai' => function (array $attributes) {
                $urutan = max(1, (int) ($attributes['urutan'] ?? 1));
                $startMinutes = 7 * 60 + ($urutan - 1) * 35;

                return sprintf('%02d:%02d', intdiv($startMinutes, 60), $startMinutes % 60);
            },
            'jam_selesai' => function (array $attributes) {
                $urutan = max(1, (int) ($attributes['urutan'] ?? 1));
                $endMinutes = 7 * 60 + $urutan * 35;

                return sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);
            },
            'is_pelajaran' => true,
        ];
    }
}
