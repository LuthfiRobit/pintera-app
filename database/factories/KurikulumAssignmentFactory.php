<?php

namespace Database\Factories;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class KurikulumAssignmentFactory extends Factory
{
    protected $model = KurikulumAssignment::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'bentuk_pendidikan' => $this->faker->randomElement(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB']),
            'tingkat' => (string) $this->faker->numberBetween(1, 6),
            'kurikulum' => $this->faker->randomElement(KurikulumFramework::cases()),
        ];
    }
}
