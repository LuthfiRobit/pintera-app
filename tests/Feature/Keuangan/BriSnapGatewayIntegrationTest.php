<?php

namespace Tests\Feature\Keuangan;

use App\Exceptions\BriApiException;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;
use App\Services\Finance\Gateway\BriSnapGateway;
use App\Domains\Keuangan\Models\Pembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class BriSnapGatewayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bri.merchant_id' => 'MERCHANT123',
            'services.bri.terminal_id' => 'TERM123',
        ]);
    }

    public function test_create_qris_returns_qris_result()
    {
        $mockClient = Mockery::mock(BriSnapClient::class);
        
        $pembayaran = Pembayaran::factory()->create([
            'amount' => 50000
        ]);

        $expectedPayload = [
            'partnerReferenceNo' => str_pad((string) $pembayaran->id, 6, '0', STR_PAD_LEFT),
            'amount' => [
                'value' => '50000.00',
                'currency' => 'IDR'
            ],
            'merchantId' => 'MERCHANT123',
            'terminalId' => 'TERM123',
        ];

        $mockClient->shouldReceive('post')
            ->once()
            ->with('/snap/v1.1/qr/qr-mpm-generate', $expectedPayload)
            ->andReturn([
                'responseCode' => '2004700',
                'qrContent' => '000201010212...',
                'referenceNo' => 'REF999888'
            ]);

        $gateway = new BriSnapGateway($mockClient);
        
        $result = $gateway->createQris($pembayaran, 'DIRECT');
        
        $this->assertEquals('000201010212...', $result->qrCodeData);
        $this->assertEquals(50000, $result->amount);
        $this->assertEquals('REF999888', $result->payload['referenceNo']);
    }

    public function test_check_status_qris_returns_paid()
    {
        $mockClient = Mockery::mock(BriSnapClient::class);
        
        $expectedPayload = [
            'originalReferenceNo' => 'REF999888',
            'serviceCode' => '47',
            'additionalInfo' => [
                'terminalId' => 'TERM123'
            ]
        ];

        $mockClient->shouldReceive('post')
            ->once()
            ->with('/snap/v1.1/qr/qr-mpm-query', $expectedPayload)
            ->andReturn([
                'responseCode' => '2005100',
                'latestTransactionStatus' => '00',
                'amount' => [
                    'value' => '50000.00'
                ]
            ]);

        $gateway = new BriSnapGateway($mockClient);
        
        $result = $gateway->checkStatus('REF999888', 'qris');
        
        $this->assertEquals('PAID', $result->status);
        $this->assertEquals('00', $result->payload['latestTransactionStatus']);
    }

    public function test_check_status_qris_returns_waiting()
    {
        $mockClient = Mockery::mock(BriSnapClient::class);
        
        $mockClient->shouldReceive('post')
            ->once()
            ->andReturn([
                'responseCode' => '2005100',
                'latestTransactionStatus' => '03',
            ]);

        $gateway = new BriSnapGateway($mockClient);
        
        $result = $gateway->checkStatus('REF999888', 'qris');
        
        $this->assertEquals('WAITING', $result->status);
    }

    public function test_check_status_qris_returns_failed()
    {
        $mockClient = Mockery::mock(BriSnapClient::class);
        
        $mockClient->shouldReceive('post')
            ->once()
            ->andReturn([
                'responseCode' => '2005100',
                'latestTransactionStatus' => '06',
            ]);

        $gateway = new BriSnapGateway($mockClient);
        
        $result = $gateway->checkStatus('REF999888', 'qris');

        $this->assertEquals('FAILED', $result->status);
    }

    public function test_check_status_va_throws_not_implemented_exception()
    {
        $mockClient = Mockery::mock(BriSnapClient::class);

        $gateway = new BriSnapGateway($mockClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BriSnapGateway VA checkStatus not fully implemented yet');

        $gateway->checkStatus('REF999888', 'va');
    }

    public function test_create_qris_propagates_bri_api_exception_from_client()
    {
        $mockClient = Mockery::mock(BriSnapClient::class);

        $pembayaran = Pembayaran::factory()->create([
            'amount' => 50000
        ]);

        $mockClient->shouldReceive('post')
            ->once()
            ->with('/snap/v1.1/qr/qr-mpm-generate', Mockery::type('array'))
            ->andThrow(new BriApiException('5004700', 'General Error'));

        $gateway = new BriSnapGateway($mockClient);

        $this->expectException(BriApiException::class);

        $gateway->createQris($pembayaran, 'DIRECT');
    }

    public function test_create_virtual_account_generates_local_va_number_without_http_call()
    {
        config(['services.bri.inbound.partner_service_id' => '77777777']);

        Http::fake([
            '*' => Http::response(['error' => 'should not be called'], 500),
        ]);

        $client = BriSnapClient::fromConfig();
        $gateway = new BriSnapGateway($client);

        $siswa = \App\Models\Siswa::factory()->create();
        $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id]);

        $result = $gateway->createVirtualAccount($pembayaran, 'WALLET_PERMANENT');

        $expectedCustomerNo = str_pad((string) $siswa->id, 20, '0', STR_PAD_LEFT);
        $this->assertSame('77777777' . $expectedCustomerNo, $result->vaNumber);
        $this->assertNull($result->amount);
        $this->assertNull($result->expiredAt);

        Http::assertNothingSent();
    }
}
