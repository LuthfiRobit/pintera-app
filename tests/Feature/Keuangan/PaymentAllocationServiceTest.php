<?php

namespace Tests\Feature\Keuangan;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_updates_paid_amount_and_status()
    {
        $tagihan1 = Tagihan::factory()->create([
            'total_tagihan' => 100000,
            'net_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'belum_bayar'
        ]);
        
        $tagihan2 = Tagihan::factory()->create([
            'total_tagihan' => 50000,
            'net_amount' => 50000,
            'paid_amount' => 20000,
            'status' => 'sebagian'
        ]);

        $pembayaran = Pembayaran::factory()->create([
            'status' => 'lunas'
        ]);

        PembayaranTagihan::create([
            'pembayaran_id' => $pembayaran->id,
            'tagihan_id' => $tagihan1->id,
            'amount_allocated' => 50000
        ]);

        PembayaranTagihan::create([
            'pembayaran_id' => $pembayaran->id,
            'tagihan_id' => $tagihan2->id,
            'amount_allocated' => 30000
        ]);

        $service = app(PaymentAllocationService::class);
        $service->allocate($pembayaran);

        $tagihan1->refresh();
        $this->assertEquals(50000, $tagihan1->paid_amount);
        $this->assertEquals('sebagian', $tagihan1->status);

        $tagihan2->refresh();
        $this->assertEquals(50000, $tagihan2->paid_amount);
        $this->assertEquals('lunas', $tagihan2->status);
    }

    public function test_allocation_skips_cancelled_bills()
    {
        $tagihan = Tagihan::factory()->create([
            'total_tagihan' => 100000,
            'net_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'dibatalkan'
        ]);

        $pembayaran = Pembayaran::factory()->create([
            'status' => 'lunas'
        ]);

        PembayaranTagihan::create([
            'pembayaran_id' => $pembayaran->id,
            'tagihan_id' => $tagihan->id,
            'amount_allocated' => 100000
        ]);

        $service = app(PaymentAllocationService::class);
        $service->allocate($pembayaran);

        $tagihan->refresh();
        $this->assertEquals(0, $tagihan->paid_amount); // Should not increase
        $this->assertEquals('dibatalkan', $tagihan->status);
    }
}
