<?php

namespace Tests\Feature\Keuangan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriVaInboundInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
            'services.bri.inbound.partner_service_id' => '77777777',
        ]);

        $tokenResponse = $this->postJson('/snap/v1.0/access-token/b2b', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);
        $this->token = $tokenResponse->json('accessToken');
    }

    public function test_inquiry_returns_siswa_name_and_suggested_amount()
    {
        $siswa = Siswa::factory()->create(['nama_lengkap' => 'Budi Santoso']);
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);

        $jenisTagihan = JenisTagihan::factory()->create();
        $tagihan = Tagihan::factory()->create([
            'tagihable_type' => Siswa::class,
            'tagihable_id' => $siswa->id,
            'status' => 'belum_bayar',
            'net_amount' => 350000,
            'paid_amount' => 0,
            'jatuh_tempo' => now()->addDays(5),
        ]);
        TagihanItem::factory()->create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/inquiry', [
                'virtualAccountNo' => $va->va_number,
                'inquiryRequestId' => 'test-inquiry-001',
            ]);

        $response->assertOk();
        $response->assertJson([
            'responseCode' => '2002400',
            'virtualAccountData' => [
                'virtualAccountNo' => $va->va_number,
                'virtualAccountName' => 'Budi Santoso',
                'totalAmount' => ['value' => '350000.00', 'currency' => 'IDR'],
            ],
        ]);
    }

    public function test_inquiry_does_not_change_any_data()
    {
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $walletBalanceBefore = $siswa->wallet->fresh()->balance;
        $pembayaranCountBefore = \App\Models\Pembayaran::count();
        $tagihanCountBefore = Tagihan::count();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/inquiry', [
                'virtualAccountNo' => $va->va_number,
                'inquiryRequestId' => 'test-inquiry-002',
            ]);

        $this->assertSame($walletBalanceBefore, $siswa->wallet->fresh()->balance);
        $this->assertSame($pembayaranCountBefore, \App\Models\Pembayaran::count());
        $this->assertSame($tagihanCountBefore, Tagihan::count());
    }

    public function test_inquiry_rejects_invalid_token()
    {
        $response = $this->withHeader('Authorization', 'Bearer token-palsu')
            ->postJson('/snap/v1.0/transfer-va/inquiry', ['virtualAccountNo' => '7777777700000000000001']);

        $response->assertStatus(401);
    }

    public function test_inquiry_returns_404_for_unknown_va_number()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/inquiry', ['virtualAccountNo' => '9999999900000000000001']);

        $response->assertStatus(404);
    }
}
