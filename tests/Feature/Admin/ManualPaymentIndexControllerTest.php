<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatAdminKeuanganUntukIndexManualPayment(): array
{
    Permission::firstOrCreate(['name' => 'pembayaran.verifikasi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('pembayaran.verifikasi');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

function buatManualPaymentRequestPending(Lembaga $lembaga, string $siswaNama = 'Anak Verifikasi'): ManualPaymentRequest
{
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => $siswaNama]);
    $jenis = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenis->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    return ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => User::factory()->create()->id, 'amount' => 100000,
        'transfer_proof_path' => 'bukti-transfer/x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);
}

it('denies access without pembayaran.verifikasi permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.manual-payment.index'))->assertForbidden();
});

it('lists only PENDING requests for the admin own lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukIndexManualPayment();
    $pendingOwn = buatManualPaymentRequestPending($lembaga, 'Anak Lembaga Sendiri');

    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    buatManualPaymentRequestPending($otherLembaga, 'Anak Lembaga Lain');

    $response = $this->actingAs($user)->get(route('admin.manual-payment.index'));

    $response->assertOk();
    $response->assertSee('Anak Lembaga Sendiri');
    $response->assertDontSee('Anak Lembaga Lain');
});

it('excludes already-processed (non-PENDING) requests', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukIndexManualPayment();
    $approved = buatManualPaymentRequestPending($lembaga, 'Anak Sudah Disetujui');
    $approved->update(['status' => 'APPROVED']);

    $response = $this->actingAs($user)->get(route('admin.manual-payment.index'));

    $response->assertOk();
    $response->assertDontSee('Anak Sudah Disetujui');
});

it('returns only the table partial for an AJAX request', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukIndexManualPayment();
    buatManualPaymentRequestPending($lembaga);

    $response = $this->actingAs($user)->get(route('admin.manual-payment.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertDontSee('<x-app-layout', false);
});

it('filters by search on siswa name', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukIndexManualPayment();
    buatManualPaymentRequestPending($lembaga, 'Budi Santoso');
    buatManualPaymentRequestPending($lembaga, 'Siti Aminah');

    $response = $this->actingAs($user)->get(route('admin.manual-payment.index', ['search' => 'Budi']));

    $response->assertOk();
    $response->assertSee('Budi Santoso');
    $response->assertDontSee('Siti Aminah');
});
