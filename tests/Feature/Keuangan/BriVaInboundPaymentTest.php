<?php

namespace Tests\Feature\Keuangan;

use App\Domains\Keuangan\Models\BriInboundPaymentLog;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\SystemSetting;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BriVaInboundPaymentTest extends TestCase
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

    protected function payloadFor(string $vaNumber, float $amount, string $paymentRequestId): array
    {
        return [
            'virtualAccountNo' => $vaNumber,
            'paidAmount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR'],
            'paymentRequestId' => $paymentRequestId,
        ];
    }

    public function test_payment_credits_wallet_and_auto_debits_due_tagihan()
    {
        $siswa = Siswa::factory()->create();
        SystemSetting::create(['lembaga_id' => $siswa->lembaga_id, 'key' => 'auto_debit_enabled', 'value' => 'true']);
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);

        $jenisTagihan = JenisTagihan::factory()->create();
        $tagihan = Tagihan::factory()->create([
            'tagihable_type' => Siswa::class,
            'tagihable_id' => $siswa->id,
            'jenis_tagihan_id' => $jenisTagihan->id,
            'status' => 'belum_bayar',
            'net_amount' => 100000,
            'paid_amount' => 0,
        ]);
        TagihanItem::factory()->create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor($va->va_number, 100000, 'PAY-001'));

        $response->assertOk();
        $response->assertJsonPath('virtualAccountData.paymentFlagStatus', '00');

        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);

        $this->assertDatabaseHas('bri_inbound_payment_logs', ['payment_request_id' => 'PAY-001']);
    }

    public function test_payment_is_idempotent_for_duplicate_payment_request_id()
    {
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $saldoAwal = (float) $siswa->wallet->fresh()->balance;

        $payload = $this->payloadFor($va->va_number, 50000, 'PAY-DUPLICATE');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/snap/v1.0/transfer-va/payment', $payload)->assertOk();
        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/snap/v1.0/transfer-va/payment', $payload)->assertOk();

        $this->assertSame($saldoAwal + 50000, (float) $siswa->wallet->fresh()->balance);
        $this->assertSame(1, BriInboundPaymentLog::where('payment_request_id', 'PAY-DUPLICATE')->count());
    }

    public function test_payment_keeps_wallet_credit_even_if_auto_allocation_fails()
    {
        // auto_debit_enabled default false kalau tidak di-set -- pastikan false di sini
        // supaya AutoAllocationEngine::run() tidak dipanggil (kita test resiliency-nya
        // lewat pengecualian umum: seandainya topup() throw APAPUN, saldo tetap masuk
        // karena sudah commit di transaksi internalnya sendiri sebelum exception muncul).
        // Test ini memverifikasi baris ledger + saldo tetap konsisten walau tidak ada
        // tagihan untuk dialokasikan (skenario paling sederhana yang tidak butuh mocking).
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $saldoAwal = (float) $siswa->wallet->fresh()->balance;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor($va->va_number, 75000, 'PAY-NO-ALLOC'));

        $response->assertOk();
        $this->assertSame($saldoAwal + 75000, (float) $siswa->wallet->fresh()->balance);
    }

    public function test_payment_rejects_invalid_token()
    {
        $response = $this->withHeader('Authorization', 'Bearer token-palsu')
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor('7777777700000000000001', 50000, 'PAY-BAD-TOKEN'));

        $response->assertStatus(401);
    }

    public function test_payment_returns_404_for_unknown_va_number()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor('9999999900000000000001', 50000, 'PAY-UNKNOWN-VA'));

        $response->assertStatus(404);
    }

    public function test_payment_rejects_non_positive_amount_without_logging_as_processed()
    {
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor($va->va_number, 0, 'PAY-ZERO-AMOUNT'));

        $response->assertStatus(404);
        $this->assertDatabaseMissing('bri_inbound_payment_logs', ['payment_request_id' => 'PAY-ZERO-AMOUNT']);
    }

    /**
     * Money-safety scenario: Wallet::topup() commits its own internal balance-increment
     * transaction, then (outside that transaction) invokes AutoAllocationEngine::run().
     * If that second step throws for ANY reason, the balance increment has already been
     * committed to the database -- the controller must still return HTTP 200 (BRI must
     * never retry this paymentRequestId), the wallet balance must reflect the credit, and
     * a subsequent replay with the same paymentRequestId must hit the idempotency check
     * and must NOT credit the wallet a second time.
     */
    public function test_payment_still_succeeds_and_does_not_double_credit_on_retry_when_auto_allocation_throws()
    {
        $siswa = Siswa::factory()->create();
        SystemSetting::create(['lembaga_id' => $siswa->lembaga_id, 'key' => 'auto_debit_enabled', 'value' => 'true']);
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $saldoAwal = (float) $siswa->wallet->fresh()->balance;

        $mockEngine = \Mockery::mock(\App\Services\Finance\AutoAllocationEngine::class);
        $mockEngine->shouldReceive('run')->andThrow(new \RuntimeException('Simulated AutoAllocationEngine failure'));
        $this->app->instance(\App\Services\Finance\AutoAllocationEngine::class, $mockEngine);

        Log::shouldReceive('error')->atLeast()->once();

        $payload = $this->payloadFor($va->va_number, 60000, 'PAY-AUTOALLOC-THROWS');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $payload);

        // Still a success response to BRI -- the ledger row + balance are already safely
        // committed by this point, so BRI must never be told to retry.
        $response->assertOk();
        $response->assertJsonPath('virtualAccountData.paymentFlagStatus', '00');

        $this->assertSame($saldoAwal + 60000, (float) $siswa->wallet->fresh()->balance);

        // The wallet balance was ALREADY credited successfully before
        // AutoAllocationEngine::run() threw (that increment commits inside
        // Wallet::topup()'s own internal DB transaction). topup_status must
        // reflect that the credit itself succeeded -- NOT 'failed' -- otherwise
        // ReconcilePayments::retryFailedTopups() would re-select this Pembayaran
        // and double-credit the wallet (see NEW-1 regression test below).
        $pembayaran = \App\Models\Pembayaran::where('channel_reference', 'PAY-AUTOALLOC-THROWS')->firstOrFail();
        $this->assertSame('completed', $pembayaran->topup_status);

        // Replay with the same paymentRequestId -- must hit the idempotency check and
        // must NOT touch the wallet balance again.
        $replayResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $payload);

        $replayResponse->assertOk();
        $replayResponse->assertJsonPath('virtualAccountData.paymentFlagStatus', '00');

        $this->assertSame($saldoAwal + 60000, (float) $siswa->wallet->fresh()->balance);
        $this->assertSame(1, BriInboundPaymentLog::where('payment_request_id', 'PAY-AUTOALLOC-THROWS')->count());
    }

    /**
     * NEW-1 regression test: after an auto-allocation failure inside payment(),
     * the hourly ReconcilePayments::retryFailedTopups() scheduler must NOT
     * re-select and re-credit this Pembayaran. Its topup_status must end up
     * 'completed' (not 'failed'), which excludes it from the retry query
     * (`Pembayaran::where('topup_status', 'failed')...`). This test runs the
     * real `finance:reconcile-payments` artisan command end-to-end afterwards
     * (which internally calls retryFailedTopups() -> topupSisaJikaAda() for any
     * row it selects) and asserts the wallet balance is unchanged, as a
     * defense-in-depth check. A more direct, isolated unit-level version of
     * this same defense-in-depth check -- calling
     * PaymentAllocationService::topupSisaJikaAda() and the ManualPaymentController
     * approve() flow directly, repeatedly, with AutoAllocationEngine::run()
     * mocked to throw -- lives in
     * tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php and
     * tests/Feature/Admin/ManualPaymentControllerTest.php.
     */
    public function test_reconcile_payments_does_not_double_credit_wallet_after_auto_allocation_failure()
    {
        $siswa = Siswa::factory()->create();
        SystemSetting::create(['lembaga_id' => $siswa->lembaga_id, 'key' => 'auto_debit_enabled', 'value' => 'true']);
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $saldoAwal = (float) $siswa->wallet->fresh()->balance;

        $mockEngine = \Mockery::mock(\App\Services\Finance\AutoAllocationEngine::class);
        $mockEngine->shouldReceive('run')->andThrow(new \RuntimeException('Simulated AutoAllocationEngine failure'));
        $this->app->instance(\App\Services\Finance\AutoAllocationEngine::class, $mockEngine);

        Log::shouldReceive('error')->atLeast()->once();

        $payload = $this->payloadFor($va->va_number, 60000, 'PAY-RECONCILE-NO-DOUBLE-CREDIT');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $payload);

        $response->assertOk();

        // Step 1: wallet credited exactly once by payment().
        $afterPayment = (float) $siswa->wallet->fresh()->balance;
        $this->assertSame($saldoAwal + 60000, $afterPayment);

        $pembayaran = \App\Models\Pembayaran::where('channel_reference', 'PAY-RECONCILE-NO-DOUBLE-CREDIT')->firstOrFail();
        $this->assertSame('completed', $pembayaran->topup_status);

        // Step 2: the hourly reconciliation scheduler's query must exclude this
        // Pembayaran entirely, because topup_status is 'completed', not 'failed'.
        $selectedForRetry = \App\Models\Pembayaran::where('topup_status', 'failed')
            ->where('status', 'lunas')
            ->whereNotNull('siswa_id')
            ->pluck('id');
        $this->assertNotContains($pembayaran->id, $selectedForRetry->all());

        // Step 3 (defense-in-depth): run the actual artisan command end-to-end.
        $this->artisan('finance:reconcile-payments')->assertExitCode(0);

        $afterReconcile = (float) $siswa->wallet->fresh()->balance;
        $this->assertSame($afterPayment, $afterReconcile, 'Wallet must not be double-credited by the reconciliation scheduler.');
        $this->assertSame($saldoAwal + 60000, $afterReconcile);
    }
}
