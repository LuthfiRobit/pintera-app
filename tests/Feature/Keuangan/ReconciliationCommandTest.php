<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\Models\BriVirtualAccount;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\Wallet;
use App\DTO\PaymentStatusResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use App\Services\Finance\PaymentAllocationService;

class ReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bri.gateway' => 'mock']);
    }

    public function test_reconcile_command_updates_waiting_payments()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('checkStatus')
                    ->with('1234567890', 'va')
                    ->andReturn(new PaymentStatusResult('PAID', ['check' => true]));
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

        $this->artisan('finance:reconcile-payments')
             ->expectsOutputToContain('Reconciliation completed')
             ->assertExitCode(0);

        $va->refresh();
        $this->assertEquals('PAID', $va->status);

        $pembayaran->refresh();
        $this->assertEquals('lunas', $pembayaran->status);

        $tagihan->refresh();
        $this->assertEquals(100000, $tagihan->paid_amount);
        $this->assertEquals('lunas', $tagihan->status);
    }

    public function test_reconcile_command_retries_failed_topups()
    {
        $siswa = Siswa::factory()->create();
        $wallet = $siswa->wallet;
        $wallet->balance = 0;
        $wallet->save();

        $pembayaran = Pembayaran::factory()->create([
            'siswa_id' => $siswa->id,
            'metode' => 'va_bri',
            'amount' => 50000,
            'status' => 'lunas',
            'topup_status' => 'failed'
        ]);

        $va = BriVirtualAccount::factory()->create([
            'wallet_id' => $wallet->id,
            'pembayaran_id' => $pembayaran->id,
            'va_type' => 'WALLET_PERMANENT',
            'va_number' => '9876543210',
            'amount' => 50000,
            'status' => 'PERMANENT'
        ]);

        $this->artisan('finance:reconcile-payments')
             ->expectsOutputToContain('Reconciliation completed')
             ->assertExitCode(0);

        $pembayaran->refresh();
        $this->assertEquals('completed', $pembayaran->topup_status);

        $wallet->refresh();
        $this->assertEquals(50000, $wallet->balance);
    }
}
