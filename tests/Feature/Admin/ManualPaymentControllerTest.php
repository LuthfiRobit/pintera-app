<?php
// tests/Feature/Admin/ManualPaymentControllerTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Domains\Keuangan\Models\Wallet;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatAdminKeuanganUntukManualPayment(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

it('denies approve without pembayaran.verifikasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest))->assertForbidden();
});

it('approves a BILL-PAYMENT manual request: allocates tagihan inside one transaction, never touches Wallet::topup()', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);
    $walletSaldoAwal = (float) $siswa->wallet->balance;

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertRedirect();
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('APPROVED');
    expect($manualRequest->reviewed_by)->toBe($user->id);

    $tagihan->refresh();
    expect($tagihan->status)->toBe('lunas');
    expect((float) $tagihan->paid_amount)->toBe(100000.0);

    // Wallet::topup() harus SAMA SEKALI tidak terlibat untuk kasus bill-payment.
    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal);
});

it('approves a TOPUP manual request: calls Wallet::topup() outside the transaction, marks topup_status completed', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $walletSaldoAwal = (float) $siswa->wallet->balance;

    $pembayaran = app(\App\Domains\Keuangan\Services\PaymentService::class)->createManualTopupPayment($siswa, [
        'amount' => 200000, 'requested_by' => $user->id, 'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(),
    ]);
    $manualRequest = ManualPaymentRequest::where('pembayaran_id', $pembayaran->id)->first();

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertRedirect();
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('APPROVED');

    $pembayaran->refresh();
    expect($pembayaran->topup_status)->toBe('completed');

    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal + 200000.0);
});

it('marks a TOPUP manual request completed (not failed) when AutoAllocationEngine::run() throws after the wallet was already credited, and does not double-credit on a defense-in-depth retry (round-3 double-credit regression)', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Models\SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => 'true']);
    $walletSaldoAwal = (float) $siswa->wallet->balance;

    $mockEngine = \Mockery::mock(\App\Domains\Keuangan\Services\AutoAllocationEngine::class);
    $mockEngine->shouldReceive('run')->andThrow(new \RuntimeException('Simulated AutoAllocationEngine failure'));
    app()->instance(\App\Domains\Keuangan\Services\AutoAllocationEngine::class, $mockEngine);

    \Illuminate\Support\Facades\Log::shouldReceive('error')->atLeast()->once();

    $pembayaran = app(\App\Domains\Keuangan\Services\PaymentService::class)->createManualTopupPayment($siswa, [
        'amount' => 200000, 'requested_by' => $user->id, 'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(),
    ]);
    $manualRequest = ManualPaymentRequest::where('pembayaran_id', $pembayaran->id)->first();

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertRedirect();
    $pembayaran->refresh();
    // Balance was genuinely credited -- only the auto-allocation step failed --
    // so topup_status must be 'completed', not 'failed', otherwise
    // ReconcilePayments::retryFailedTopups() would re-select and re-credit it.
    expect($pembayaran->topup_status)->toBe('completed');

    $balanceAfterApprove = (float) $siswa->wallet->fresh()->balance;
    expect($balanceAfterApprove)->toBe($walletSaldoAwal + 200000.0);

    // Defense-in-depth: even if topupSisaJikaAda() were called again directly on
    // this Pembayaran (what retryFailedTopups() would do if it wrongly selected
    // it), it must be a safe no-op given topup_status is already 'completed'.
    app(\App\Domains\Keuangan\Services\PaymentAllocationService::class)->topupSisaJikaAda($pembayaran->fresh());

    expect((float) $siswa->wallet->fresh()->balance)->toBe($balanceAfterApprove);
});

it('blocks cross-tenant approve/reject: admin_keuangan in lembaga A cannot touch a manual payment request belonging to lembaga B', function () {
    [$userA, $lembagaA] = buatAdminKeuanganUntukManualPayment();
    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $walletSaldoAwal = (float) $siswaB->wallet->balance;
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswaB->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $userA->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($userA)->post(route('admin.manual-payment.approve', $manualRequest))->assertNotFound();
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('PENDING');
    $pembayaran->refresh();
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
    expect((float) $siswaB->wallet->fresh()->balance)->toBe($walletSaldoAwal);

    $this->actingAs($userA)->post(route('admin.manual-payment.reject', $manualRequest), ['rejection_reason' => 'test'])->assertNotFound();
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('PENDING');
    $pembayaran->refresh();
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
});

it('rejects the guard scenario: topup_status is pending but pembayaran_tagihan rows ALSO exist — must 500, not silently pick a branch', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    // Data yang sengaja dibuat TIDAK KONSISTEN: topup_status='pending' TAPI juga punya pembayaran_tagihan.
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi', 'topup_status' => 'pending']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertStatus(500);
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('PENDING'); // tidak berubah — guard mencegah proses apa pun
});

it('rejects the guard scenario: topup_status is none and there are NO pembayaran_tagihan rows either — must 500, not silently pick a branch', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    // Data tidak konsisten: bukan bill-payment (tidak ada pembayaran_tagihan) DAN bukan topup (topup_status=none).
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertStatus(500);
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('PENDING');
});

it('rejects approve/reject on a request that is not PENDING (idempotency guard)', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'lunas', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'APPROVED',
        'reviewed_by' => $user->id, 'reviewed_at' => now(),
    ]);

    $walletSaldoAwal = (float) $siswa->wallet->balance;

    $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest))->assertStatus(422);
    $pembayaran->refresh();
    expect($pembayaran->status)->toBe('lunas');
    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal);

    $this->actingAs($user)->post(route('admin.manual-payment.reject', $manualRequest), ['rejection_reason' => 'test'])->assertStatus(422);
    $pembayaran->refresh();
    expect($pembayaran->status)->toBe('lunas');
    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal);
});

it('rejects a manual payment request: sets status ditolak, requires rejection_reason, never touches wallet', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $walletSaldoAwal = (float) $siswa->wallet->balance;
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($user)->post(route('admin.manual-payment.reject', $manualRequest), [])->assertSessionHasErrors('rejection_reason');

    $response = $this->actingAs($user)->post(route('admin.manual-payment.reject', $manualRequest), ['rejection_reason' => 'Bukti tidak valid']);
    $response->assertRedirect();

    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('REJECTED');
    expect($manualRequest->rejection_reason)->toBe('Bukti tidak valid');
    $pembayaran->refresh();
    expect($pembayaran->status)->toBe('ditolak');
    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal);
});
