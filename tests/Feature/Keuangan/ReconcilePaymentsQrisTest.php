<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\DTO\PaymentStatusResult;
use App\Domains\Keuangan\Models\BriQrisPayment;
use App\Domains\Keuangan\Models\Pembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class ReconcilePaymentsQrisTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_calls_check_status_with_reference_no_not_qr_code()
    {
        $pembayaran = Pembayaran::factory()->create([
            'status' => 'menunggu_pembayaran',
        ]);

        $qris = BriQrisPayment::create([
            'pembayaran_id' => $pembayaran->id,
            'qris_type' => 'DIRECT',
            'amount' => 10000,
            'qr_code' => 'QR-CONTENT-XYZ',
            'reference_no' => 'REF-ABC-123',
            'expired_at' => now()->addMinutes(15),
            'status' => 'WAITING',
        ]);

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('checkStatus')
            ->once()
            ->with('REF-ABC-123', 'qris')
            ->andReturn(new PaymentStatusResult('WAITING', []));

        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        Artisan::call('finance:reconcile-payments');

        $qris->refresh();
        $this->assertEquals('WAITING', $qris->status);
    }

    public function test_reconcile_skips_qris_rows_with_null_reference_no()
    {
        $pembayaran = Pembayaran::factory()->create([
            'status' => 'menunggu_pembayaran',
        ]);

        BriQrisPayment::create([
            'pembayaran_id' => $pembayaran->id,
            'qris_type' => 'DIRECT',
            'amount' => 10000,
            'qr_code' => 'QR-CONTENT-LEGACY',
            'reference_no' => null,
            'expired_at' => now()->addMinutes(15),
            'status' => 'WAITING',
        ]);

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('checkStatus')->never();

        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        Artisan::call('finance:reconcile-payments');

        $this->assertTrue(true);
    }
}
