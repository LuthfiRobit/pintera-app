<?php
// database/factories/KasusTugasFactory.php

namespace Database\Factories;

use App\Domains\Kasus\Enums\StatusKasusTugas;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KasusTugasFactory extends Factory
{
    protected $model = KasusTugas::class;

    public function definition(): array
    {
        return [
            'kasus_id' => Kasus::factory(),
            'judul' => $this->faker->sentence(3),
            'instruksi' => $this->faker->sentence(),
            'frekuensi' => 'sekali',
            // Setiap baris kasus_tugas — baik dari generator batch maupun dibuat satuan lewat
            // factory/seeder — harus punya batch_id (Global Constraint (e)): _tab-tugas.blade.php
            // mengelompokkan tampilan per batch_id, dan dua baris ber-batch_id null akan
            // tergabung ke satu grup, menyembunyikan judul salah satunya.
            'batch_id' => (string) Str::uuid(),
            'batch_urutan' => 1,
            'batch_total' => 1,
            'mulai_pada' => now()->toDateString(),
            'batas_selesai_pada' => now()->addDays(7)->toDateString(),
            'status' => StatusKasusTugas::Ditugaskan,
        ];
    }
}
