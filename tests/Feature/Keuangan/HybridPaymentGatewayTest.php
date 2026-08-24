<?php

namespace Tests\Feature\Keuangan;

use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\Gateway\BriSnapGateway;
use App\Domains\Keuangan\Services\Gateway\HybridPaymentGateway;
use App\Domains\Keuangan\Services\Gateway\MockPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HybridPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_qris_routes_to_bri_snap_gateway()
    {
        $pembayaran = Pembayaran::factory()->create();
        $expected = new QrisResult('QR-CONTENT', 10000.0, now()->addMinutes(15), []);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldReceive('createQris')->once()->with($pembayaran, 'DIRECT')->andReturn($expected);
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldNotReceive('createQris');

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->createQris($pembayaran, 'DIRECT');

        $this->assertSame($expected, $result);
    }

    public function test_create_virtual_account_routes_to_mock_gateway()
    {
        $pembayaran = Pembayaran::factory()->create();
        $expected = new VirtualAccountResult('MOCK-VA-000001', 10000, now()->addHours(24), []);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldNotReceive('createVirtualAccount');
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldReceive('createVirtualAccount')->once()->with($pembayaran, 'BILL_DIRECT')->andReturn($expected);

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->createVirtualAccount($pembayaran, 'BILL_DIRECT');

        $this->assertSame($expected, $result);
    }

    public function test_check_status_qris_routes_to_bri_snap_gateway()
    {
        $expected = new PaymentStatusResult('PAID', []);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldReceive('checkStatus')->once()->with('REF-1', 'qris')->andReturn($expected);
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldNotReceive('checkStatus');

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->checkStatus('REF-1', 'qris');

        $this->assertSame($expected, $result);
    }

    public function test_check_status_va_routes_to_mock_gateway()
    {
        $expected = new PaymentStatusResult('PAID', ['mock_response' => true]);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldNotReceive('checkStatus');
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldReceive('checkStatus')->once()->with('VA-1', 'va')->andReturn($expected);

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->checkStatus('VA-1', 'va');

        $this->assertSame($expected, $result);
    }

    public function test_verify_callback_signature_routes_to_mock_gateway()
    {
        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldNotReceive('verifyCallbackSignature');
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldReceive('verifyCallbackSignature')->once()->with('payload', 'sig')->andReturn(true);

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $this->assertTrue($hybrid->verifyCallbackSignature('payload', 'sig'));
    }
}
