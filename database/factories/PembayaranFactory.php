<?php

namespace Database\Factories;

use App\Models\Pembayaran;
use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pembayaran> */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'tagihan_id' => Tagihan::factory(),
            'sumber' => 'calon_siswa',
            'metode' => 'transfer_manual',
            'status' => 'menunggu_verifikasi',
        ];
    }
}
