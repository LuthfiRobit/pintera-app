<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\JenisTagihan;
use App\Models\TagihanItem;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Exceptions\PaymentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure mock gateway is bound
        config(['services.bri.gateway' => 'mock']);
        
        // AppServiceProvider already binds it, but just in case:
        $this->app->forgetInstance(PaymentGatewayInterface::class);

        $this->service = app()->make(PaymentService::class);
    }

    public function test_create_va_payment_success()
    {
        $siswa = Siswa::factory()->create();
        
        $jenisTagihan = JenisTagihan::factory()->create([
            'va_expire_hours' => 24
        ]);

        $tagihan = Tagihan::factory()->create([
            'status' => 'belum_bayar'
        ]);
        
        TagihanItem::factory()->create([
            'tagihan_id' => $tagihan->id,
            'jenis_tagihan_id' => $jenisTagihan->id
        ]);

        $pembayaran = $this->service->createVaPayment($siswa, collect([$tagihan]));

        $this->assertInstanceOf(Pembayaran::class, $pembayaran);
        $this->assertEquals('va_bri', $pembayaran->metode);
        $this->assertEquals('menunggu_pembayaran', $pembayaran->status);
        $this->assertNotNull($pembayaran->briVirtualAccount);
        $this->assertStringStartsWith('MOCK-VA-', $pembayaran->briVirtualAccount->va_number);
    }

    public function test_cannot_create_payment_if_bill_cancelled()
    {
        $siswa = Siswa::factory()->create();
        
        $tagihan = Tagihan::factory()->create([
            'status' => 'dibatalkan'
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Terdapat tagihan yang sudah dibatalkan atau lunas.');

        $this->service->createQrisPayment($siswa, collect([$tagihan]));
    }

    public function test_get_or_create_permanent_va_idempotency()
    {
        $siswa = Siswa::factory()->create();
        // Auto wallet creation should have made a wallet for the student
        
        $va1 = $this->service->getOrCreatePermanentVa($siswa);
        $this->assertStringStartsWith('MOCK-VA-', $va1->va_number);
        $this->assertEquals('WALLET_PERMANENT', $va1->va_type);
        
        // Calling it again should return the exact same VA
        $va2 = $this->service->getOrCreatePermanentVa($siswa);
        $this->assertEquals($va1->id, $va2->id);
    }

    public function test_create_manual_payment()
    {
        $siswa = Siswa::factory()->create();
        $user = User::factory()->create();

        $tagihan = Tagihan::factory()->create([
            'status' => 'belum_bayar'
        ]);

        $dto = [
            'amount' => 50000,
            'transfer_proof_path' => 'proofs/test.jpg',
            'bank_origin' => 'BCA',
            'transfer_date' => now()->toDateString(),
            'requested_by' => $user->id,
        ];

        $pembayaran = $this->service->createManualPayment($siswa, collect([$tagihan]), $dto);

        $this->assertInstanceOf(Pembayaran::class, $pembayaran);
        $this->assertEquals('transfer_manual', $pembayaran->metode);
        $this->assertEquals('menunggu_verifikasi', $pembayaran->status);
        $this->assertNotNull($pembayaran->manualRequest);
        $this->assertEquals('proofs/test.jpg', $pembayaran->manualRequest->transfer_proof_path);
    }

    public function test_create_cash_payment_resolves_immediately()
    {
        $siswa = Siswa::factory()->create();
        $user = User::factory()->create();

        $tagihan = Tagihan::factory()->create([
            'status' => 'belum_bayar',
            'net_amount' => 50000,
            'total_tagihan' => 50000,
            'paid_amount' => 0
        ]);

        $pembayaran = $this->service->createCashPayment($siswa, collect([$tagihan]), $user->id);

        $this->assertEquals('cash', $pembayaran->metode);
        $this->assertEquals('lunas', $pembayaran->status); // Cash is instantly paid
        $this->assertEquals($user->id, $pembayaran->diverifikasi_oleh_user_id);
        
        $tagihan->refresh();
        $this->assertEquals(50000, $tagihan->paid_amount);
        $this->assertEquals('lunas', $tagihan->status);
    }
    public function test_create_qris_payment_saves_reference_no()
    {
        $siswa = Siswa::factory()->create();
        $tagihan = Tagihan::factory()->create(['status' => 'belum_bayar', 'net_amount' => 10000]);

        $mockGateway = \Mockery::mock(\App\Contracts\PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('createQris')
            ->once()
            ->andReturn(new \App\DTO\QrisResult('QR123', 10000, now()->addMinutes(15), ['referenceNo' => 'REF-001']));
            
        $this->app->instance(\App\Contracts\PaymentGatewayInterface::class, $mockGateway);

        $pembayaran = app(\App\Services\Finance\PaymentService::class)->createQrisPayment($siswa, collect([$tagihan]), 'DIRECT');

        $this->assertEquals('qris', $pembayaran->metode);
        $this->assertNotNull($pembayaran->briQrisPayment);
        $this->assertEquals('REF-001', $pembayaran->briQrisPayment->reference_no);
    }

    public function test_create_qris_payment_handles_missing_reference_no()
    {
        $siswa = Siswa::factory()->create();
        $tagihan = Tagihan::factory()->create(['status' => 'belum_bayar', 'net_amount' => 10000]);

        $mockGateway = \Mockery::mock(\App\Contracts\PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('createQris')
            ->once()
            ->andReturn(new \App\DTO\QrisResult('QR123', 10000, now()->addMinutes(15), ['someOtherField' => 'val']));
            
        $this->app->instance(\App\Contracts\PaymentGatewayInterface::class, $mockGateway);

        $pembayaran = app(\App\Services\Finance\PaymentService::class)->createQrisPayment($siswa, collect([$tagihan]), 'DIRECT');

        $this->assertNull($pembayaran->briQrisPayment->reference_no);
    }
}
