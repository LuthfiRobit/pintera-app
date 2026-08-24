<?php

namespace Tests\Feature\Keuangan;

use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\BriInboundPaymentLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateBriInboundCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulate_inbound_successfully_calls_payment_endpoint()
    {
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
        ]);

        $siswa = Siswa::factory()->create();
        $va = BriVirtualAccount::create([
            'wallet_id' => $siswa->wallet->id,
            'va_number' => '8888888800000000000123',
            'va_type' => 'WALLET_PERMANENT',
            'status' => 'PERMANENT',
        ]);

        $saldoAwal = (float) $siswa->wallet->balance;

        $this->artisan('bri:test-va-inbound', [
            'va_number' => '8888888800000000000123',
            'amount' => '50000'
        ])->assertExitCode(0)
          ->expectsOutputToContain('Token successfully generated')
          ->expectsOutputToContain('Payment successfully injected');

        $siswa->wallet->refresh();
        $this->assertEquals($saldoAwal + 50000, (float) $siswa->wallet->balance);
        $this->assertDatabaseHas('bri_inbound_payment_logs', [
            'va_number' => '8888888800000000000123',
            'amount' => 50000
        ]);
    }
}
