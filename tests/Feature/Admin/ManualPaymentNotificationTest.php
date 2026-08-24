<?php
// tests/Feature/Admin/ManualPaymentNotificationTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Models\OrangTua;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\Finance\TransferManualDisetujuiNotification;
use App\Notifications\Finance\TransferManualDitolakNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('sends TransferManualDisetujuiNotification to the kontak utama on approve', function () {
    Notification::fake();

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $admin->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($admin)->post(route('admin.manual-payment.approve', $manualRequest));

    Notification::assertSentTo($orangTua, TransferManualDisetujuiNotification::class);
});

it('sends TransferManualDitolakNotification to the kontak utama on reject, and it is urgent', function () {
    Notification::fake();

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $admin->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($admin)->post(route('admin.manual-payment.reject', $manualRequest), ['rejection_reason' => 'Bukti buram']);

    Notification::assertSentTo($orangTua, TransferManualDitolakNotification::class, function ($notification) {
        return $notification->isUrgent() === true && $notification->rejectionReason === 'Bukti buram';
    });
});

it('still redirects successfully (best-effort) when notification dispatch throws, and the underlying approval is unaffected', function () {
    // Simulasikan dispatcher yang gagal total (mis. WhatsApp API down) — approve() TIDAK BOLEH
    // ikut gagal (500) karena transaksi uang (status APPROVED, tagihan lunas) sudah commit
    // SEBELUM notifikasi dicoba kirim. Ini bukti bahwa try/catch di controller benar-benar
    // menyerap exception, bukan cuma asumsi.
    $this->mock(\App\Services\Finance\NotificationDispatcher::class, function ($mock) {
        $mock->shouldReceive('send')->andThrow(new \RuntimeException('Simulated WhatsApp API failure'));
    });

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $admin->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertRedirect();
    $response->assertSessionHas('status');
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('APPROVED');
    $tagihan->refresh();
    expect($tagihan->status)->toBe('lunas');
});
