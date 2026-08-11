<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\DTO\VirtualAccountResult;
use App\Exceptions\PaymentException;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Wallet;
use App\Services\Finance\Gateway\BriSnapGateway;
use App\Services\Finance\Gateway\MockPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GatewayImplementationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_gateway_returns_deterministic_results()
    {
        $gateway = new MockPaymentGateway();

        $siswa = Siswa::factory()->create();
        $pembayaran = Pembayaran::factory()->create();

        // 1. Create VA
        $vaResult = $gateway->createVirtualAccount($pembayaran, 'WALLET_PERMANENT');
        $this->assertInstanceOf(VirtualAccountResult::class, $vaResult);
        $this->assertStringStartsWith('MOCK-VA-', $vaResult->vaNumber);

        // 2. Create QRIS
        $qrisResult = $gateway->createQris($pembayaran, 'DIRECT');
        $this->assertInstanceOf(QrisResult::class, $qrisResult);
        $this->assertStringStartsWith('MOCK-QR-', $qrisResult->qrCodeData);

        // 3. Verify Signature
        $this->assertTrue($gateway->verifyCallbackSignature('any_payload', 'any_signature'));

        // 4. Check Status
        $statusResult = $gateway->checkStatus('MOCK-VA-12345');
        $this->assertInstanceOf(PaymentStatusResult::class, $statusResult);
        $this->assertEquals('PAID', $statusResult->status);
    }

    public function test_bri_snap_gateway_throws_not_implemented_exception()
    {
        $gateway = new BriSnapGateway();
        $pembayaran = Pembayaran::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BriSnapGateway not implemented: awaiting credentials');

        $gateway->createVirtualAccount($pembayaran, 'WALLET_PERMANENT');
    }

    public function test_gateway_binding_in_service_provider_based_on_config()
    {
        // Setup config to use mock
        Config::set('services.bri.gateway', 'mock');
        
        $this->app->forgetInstance(PaymentGatewayInterface::class);
        $resolvedGateway = app()->make(PaymentGatewayInterface::class);
        $this->assertInstanceOf(MockPaymentGateway::class, $resolvedGateway);

        // Setup config to use snap
        Config::set('services.bri.gateway', 'snap');
        
        $this->app->forgetInstance(PaymentGatewayInterface::class);
        $resolvedGatewaySnap = app()->make(PaymentGatewayInterface::class);
        $this->assertInstanceOf(BriSnapGateway::class, $resolvedGatewaySnap);
    }
}
