<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\Models\BriVirtualAccount;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
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
