<?php

namespace Tests\Unit\Models;

use App\Domains\Keuangan\Models\BriInboundPaymentLog;
use App\Domains\Keuangan\Models\Pembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriInboundPaymentLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_request_id_must_be_unique()
    {
        $pembayaran = Pembayaran::factory()->create();

        BriInboundPaymentLog::create([
            'payment_request_id' => 'DUPLICATE-ID',
            'va_number' => '7777700000000001',
            'amount' => 50000,
            'pembayaran_id' => $pembayaran->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        BriInboundPaymentLog::create([
            'payment_request_id' => 'DUPLICATE-ID',
            'va_number' => '7777700000000002',
            'amount' => 75000,
            'pembayaran_id' => $pembayaran->id,
        ]);
    }
}
