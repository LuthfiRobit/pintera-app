<?php

namespace Database\Factories;

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LembagaFactory extends Factory
{
    protected $model = Lembaga::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'npsn' => $this->faker->unique()->numerify('########'),
            'kode_lembaga' => strtoupper($this->faker->unique()->lexify('??????')),
            'nama' => 'Sekolah '.$this->faker->unique()->city(),
            'bentuk_pendidikan' => $this->faker->randomElement(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB']),
            'status_sekolah' => $this->faker->randomElement(['negeri', 'swasta']),
            'naungan' => $this->faker->randomElement(['kemendikdasmen', 'kemenag']),
        ];
    }
}
