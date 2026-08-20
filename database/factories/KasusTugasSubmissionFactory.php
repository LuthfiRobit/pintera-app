<?php
// database/factories/KasusTugasSubmissionFactory.php

namespace Database\Factories;

use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class KasusTugasSubmissionFactory extends Factory
{
    protected $model = KasusTugasSubmission::class;

    public function definition(): array
    {
        return [
            'tugas_id' => KasusTugas::factory(),
            'teks' => $this->faker->sentence(),
            'status_review' => 'menunggu_review',
        ];
    }
}
