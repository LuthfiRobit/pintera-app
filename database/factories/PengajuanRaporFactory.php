<?php

namespace Database\Factories;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class PengajuanRaporFactory extends Factory
{
    protected $model = PengajuanRapor::class;

    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'semester_id' => Semester::factory(),
            'status' => StatusPengajuanRapor::Draft,
        ];
    }
}
