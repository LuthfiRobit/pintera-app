<?php

namespace Database\Factories;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'kode' => $this->faker->unique()->word(),
            'isi_template' => 'Halo {nama_siswa}, ini template contoh.',
            'deskripsi' => $this->faker->sentence(),
        ];
    }
}
