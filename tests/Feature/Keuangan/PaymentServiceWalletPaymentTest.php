<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentException;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceWalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bri.gateway' => 'mock']);
        $this->app->forgetInstance(PaymentGatewayInterface::class);
        $this->service = app()->make(PaymentService::class);
    }

    public function test_create_wallet_payment_debits_wallet_and_allocates_tagihan()
    {
        $siswa = Siswa::factory()->create();
        $siswa->wallet->update(['balance' => 100000]);

        $tagihan = Tagihan::factory()->create([
            'tagihable_id' => $siswa->id,
            'tagihable_type' => Siswa::class,
            'status' => 'belum_bayar',
            'total_tagihan' => 60000,
            'net_amount' => 60000,
            'paid_amount' => 0,
        ]);

        $pembayaran = $this->service->createWalletPayment($siswa, collect([$tagihan]));

        $this->assertInstanceOf(Pembayaran::class, $pembayaran);
        $this->assertEquals('wallet_saldo', $pembayaran->metode);
        $this->assertEquals('lunas', $pembayaran->status);

        $siswa->wallet->refresh();
        $this->assertEquals(40000, $siswa->wallet->balance);

        $tagihan->refresh();
        $this->assertEquals('lunas', $tagihan->status);
        $this->assertEquals(60000, $tagihan->paid_amount);
    }

    public function test_create_wallet_payment_throws_when_balance_insufficient()
    {
        $siswa = Siswa::factory()->create();
        $siswa->wallet->update(['balance' => 10000]);

        $tagihan = Tagihan::factory()->create([
            'tagihable_id' => $siswa->id,
            'tagihable_type' => Siswa::class,
            'status' => 'belum_bayar',
            'total_tagihan' => 60000,
            'net_amount' => 60000,
            'paid_amount' => 0,
        ]);

        $this->expectException(InsufficientBalanceException::class);

        try {
            $this->service->createWalletPayment($siswa, collect([$tagihan]));
        } finally {
            // Assert no partial state: wallet untouched, no Pembayaran row created,
            // tagihan untouched — the whole operation must roll back atomically.
            $siswa->wallet->refresh();
            $this->assertEquals(10000, $siswa->wallet->balance);
            $this->assertEquals(0, Pembayaran::where('siswa_id', $siswa->id)->count());
            $tagihan->refresh();
            $this->assertEquals('belum_bayar', $tagihan->status);
        }
    }

    public function test_create_wallet_payment_rejects_cancelled_or_paid_tagihan()
    {
        $siswa = Siswa::factory()->create();
        $siswa->wallet->update(['balance' => 100000]);

        $tagihan = Tagihan::factory()->create([
            'tagihable_id' => $siswa->id,
            'tagihable_type' => Siswa::class,
            'status' => 'lunas',
        ]);

        $this->expectException(\App\Exceptions\PaymentException::class);

        $this->service->createWalletPayment($siswa, collect([$tagihan]));
    }

    public function test_create_wallet_payment_rejects_stale_tagihan_collection_and_does_not_double_debit()
    {
        $siswa = Siswa::factory()->create();
        $siswa->wallet->update(['balance' => 200000]);

        $tagihan = Tagihan::factory()->create([
            'tagihable_id' => $siswa->id,
            'tagihable_type' => Siswa::class,
            'status' => 'belum_bayar',
            'total_tagihan' => 100000,
            'net_amount' => 100000,
            'paid_amount' => 0,
        ]);

        // Simulate two concurrent requests both loading the tagihan collection
        // BEFORE either commits, as CheckoutController::wallet() does.
        $staleTagihans = collect([$tagihan]);

        // Request #1 succeeds: tagihan becomes lunas, wallet debited 100000.
        $this->service->createWalletPayment($siswa, $staleTagihans);

        $siswa->wallet->refresh();
        $this->assertEquals(100000, $siswa->wallet->balance);

        // Request #2 reuses the SAME now-stale Collection object (still showing
        // paid_amount=0 in PHP memory) -- it must fail cleanly, not double-charge.
        $this->expectException(PaymentException::class);

        try {
            $this->service->createWalletPayment($siswa, $staleTagihans);
        } finally {
            $siswa->wallet->refresh();
            $this->assertEquals(100000, $siswa->wallet->balance, 'Wallet must not be debited a second time.');
            $this->assertEquals(1, Pembayaran::where('siswa_id', $siswa->id)->count());
            $tagihan->refresh();
            $this->assertEquals('lunas', $tagihan->status);
            $this->assertEquals(100000, $tagihan->paid_amount);
        }
    }
}
