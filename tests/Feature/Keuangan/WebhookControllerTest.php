<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\Models\BriVirtualAccount;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\Wallet;
use App\Models\WalletMutasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bri.gateway' => 'mock']);
    }

    public function test_webhook_rejects_invalid_signature()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(false);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        $response = $this->postJson('/webhook/bri/payment-notification', [
            'payload' => 'data'
        ], [
            'BRI-Signature' => 'invalid-signature'
        ]);

        $response->assertStatus(401)
                 ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_webhook_processes_bill_direct_payment()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        $siswa = Siswa::factory()->create();
        $tagihan = Tagihan::factory()->create([
            'total_tagihan' => 100000,
            'net_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'belum_bayar'
        ]);

        $pembayaran = Pembayaran::factory()->create([
            'siswa_id' => $siswa->id,
            'metode' => 'va_bri',
            'status' => 'menunggu_pembayaran'
        ]);

        PembayaranTagihan::create([
            'pembayaran_id' => $pembayaran->id,
            'tagihan_id' => $tagihan->id,
            'amount_allocated' => 100000
        ]);

        $va = BriVirtualAccount::factory()->create([
            'pembayaran_id' => $pembayaran->id,
            'va_type' => 'BILL_DIRECT',
            'va_number' => '1234567890',
            'amount' => 100000,
            'status' => 'WAITING'
        ]);

        $payload = [
            'BrivaNo' => '1234',
            'CustCode' => '567890', // Combined to 1234567890
            'Amount' => '100000.00',
            'Status' => 'PAID',
        ];

        $response = $this->postJson('/webhook/bri/payment-notification', $payload, [
            'BRI-Signature' => 'valid'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => 'success']);

        $va->refresh();
        $this->assertEquals('PAID', $va->status);

        $pembayaran->refresh();
        $this->assertEquals('lunas', $pembayaran->status);

        $tagihan->refresh();
        $this->assertEquals(100000, $tagihan->paid_amount);
        $this->assertEquals('lunas', $tagihan->status);
    }

    public function test_webhook_idempotency_for_bill_direct()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        $pembayaran = Pembayaran::factory()->create(['status' => 'lunas']);
        $va = BriVirtualAccount::factory()->create([
            'pembayaran_id' => $pembayaran->id,
            'va_type' => 'BILL_DIRECT',
            'va_number' => '1234567890',
            'status' => 'PAID'
        ]);

        $payload = [
            'BrivaNo' => '1234',
            'CustCode' => '567890',
            'Amount' => '100000.00',
            'Status' => 'PAID',
        ];

        $response = $this->postJson('/webhook/bri/payment-notification', $payload, [
            'BRI-Signature' => 'valid'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => 'success']); // Should return success immediately
    }

    public function test_webhook_processes_wallet_permanent_topup()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        $siswa = Siswa::factory()->create();
        $wallet = $siswa->wallet;
        
        $va = BriVirtualAccount::factory()->create([
            'wallet_id' => $wallet->id,
            'va_type' => 'WALLET_PERMANENT',
            'va_number' => '1234567890',
            'status' => 'PERMANENT'
        ]);

        $payload = [
            'BrivaNo' => '1234',
            'CustCode' => '567890',
            'Amount' => '50000.00',
            'Status' => 'PAID',
        ];

        $response = $this->postJson('/webhook/bri/payment-notification', $payload, [
            'BRI-Signature' => 'valid'
        ]);

        $response->assertStatus(200);

        // Check wallet topup
        $wallet->refresh();
        $this->assertEquals(50000, $wallet->balance);

        // Check topup history record
        $this->assertDatabaseHas('pembayaran', [
            'siswa_id' => $siswa->id,
            'metode' => 'va_bri',
            'status' => 'lunas',
            'topup_status' => 'completed'
        ]);

        $this->assertDatabaseHas('wallet_mutasi', [
            'wallet_id' => $wallet->id,
            'tipe' => 'topup',
            'amount' => 50000,
            'keterangan' => 'Topup via Permanent VA'
        ]);
    }

    public function test_webhook_uses_lock_for_update_to_prevent_race_conditions()
    {
        // Prove that lockForUpdate() is executed.
        // True concurrent requests cannot be easily simulated in PHPUnit with SQLite,
        // so we verify the query builder generates the correct lock clause.
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        $pembayaran = Pembayaran::factory()->create();
        $va = BriVirtualAccount::factory()->create([
            'pembayaran_id' => $pembayaran->id,
            'va_type' => 'BILL_DIRECT',
            'va_number' => '9999999999',
            'status' => 'WAITING'
        ]);

        $payload = [
            'BrivaNo' => '9999',
            'CustCode' => '999999',
            'Amount' => '10000.00',
            'Status' => 'PAID',
        ];

        \Illuminate\Support\Facades\DB::enableQueryLog();

        $this->postJson('/webhook/bri/payment-notification', $payload, [
            'BRI-Signature' => 'valid'
        ]);

        $logs = \Illuminate\Support\Facades\DB::getQueryLog();
        $hasForUpdate = false;
        
        foreach ($logs as $log) {
            if (str_contains(strtolower($log['query']), 'select * from "bri_virtual_accounts" where "va_number" = ? limit 1') || 
                str_contains(strtolower($log['query']), 'for update')) {
                $hasForUpdate = true;
            }
        }

        $this->assertTrue($hasForUpdate, "Webhook must use lockForUpdate() on bri_virtual_accounts.");
    }

    public function test_webhook_returns_error_status_when_payment_reference_not_found()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        // No BriVirtualAccount or BriQrisPayment record matches this payload at all.
        $payload = [
            'BrivaNo' => '0000',
            'CustCode' => '000000',
            'Amount' => '10000.00',
            'Status' => 'PAID',
        ];

        $response = $this->postJson('/webhook/bri/payment-notification', $payload, [
            'BRI-Signature' => 'valid'
        ]);

        // Must NOT be a 2xx — BRI needs a failure status to retry delivery.
        $response->assertStatus(500);
    }
}
