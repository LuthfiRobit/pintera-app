<?php

namespace Tests\Feature\Keuangan;

use App\Models\BriVirtualAccount;
use App\Models\Siswa;
use App\Models\BriInboundPaymentLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Contracts\BriInboundAuthenticatorInterface;
use App\Services\Finance\BriInbound\SimpleBriInboundAuthenticator;

class BriVaInboundPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
        ]);
        $authenticator = app(BriInboundAuthenticatorInterface::class);
        $this->token = $authenticator->issueToken('test-client-id', 'test-client-secret');
    }

    public function test_payment_topups_wallet_and_returns_success()
    {
        $siswa = Siswa::factory()->create();
        $va = BriVirtualAccount::create([
            'wallet_id' => $siswa->wallet->id,
            'va_number' => '8888888800000000000001',
            'va_type' => 'WALLET_PERMANENT',
            'status' => 'PERMANENT',
        ]);

        $saldoAwal = (float) $siswa->wallet->balance;
        $reqId = 'test-pay-001';

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', [
                'virtualAccountNo' => $va->va_number,
                'paymentRequestId' => $reqId,
                'paidAmount' => [
                    'value' => '100000.00',
                    'currency' => 'IDR'
                ]
            ]);

        $response->assertOk();
        $response->assertJson([
            'responseCode' => '2002500',
            'virtualAccountData' => [
                'virtualAccountNo' => $va->va_number,
                'paymentRequestId' => $reqId,
            ]
        ]);

        $siswa->wallet->refresh();
        $this->assertSame($saldoAwal + 100000.0, (float) $siswa->wallet->balance);

        $this->assertDatabaseHas('bri_inbound_payment_logs', [
            'payment_request_id' => $reqId,
            'va_number' => $va->va_number,
            'amount' => 100000.0,
        ]);
    }

    public function test_payment_is_idempotent()
    {
        $siswa = Siswa::factory()->create();
        $va = BriVirtualAccount::create([
            'wallet_id' => $siswa->wallet->id,
            'va_number' => '8888888800000000000002',
            'va_type' => 'WALLET_PERMANENT',
            'status' => 'PERMANENT',
        ]);

        $saldoAwal = (float) $siswa->wallet->balance;
        $reqId = 'test-pay-002';

        // Call 1
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', [
                'virtualAccountNo' => $va->va_number,
                'paymentRequestId' => $reqId,
                'paidAmount' => ['value' => '50000.00', 'currency' => 'IDR']
            ]);

        // Call 2
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', [
                'virtualAccountNo' => $va->va_number,
                'paymentRequestId' => $reqId,
                'paidAmount' => ['value' => '50000.00', 'currency' => 'IDR']
            ]);

        $response->assertOk();
        $response->assertJson([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
        ]);

        $siswa->wallet->refresh();
        // Hanya ditambah sekali
        $this->assertSame($saldoAwal + 50000.0, (float) $siswa->wallet->balance);
        $this->assertSame(1, BriInboundPaymentLog::where('payment_request_id', $reqId)->count());
    }

    public function test_payment_rejects_invalid_token()
    {
        $response = $this->withHeader('Authorization', 'Bearer token-salah')
            ->postJson('/snap/v1.0/transfer-va/payment', [
                'virtualAccountNo' => '8888888800000000000001',
                'paymentRequestId' => 'test-003',
                'paidAmount' => ['value' => '10000.00', 'currency' => 'IDR']
            ]);

        $response->assertStatus(401);
    }
}
