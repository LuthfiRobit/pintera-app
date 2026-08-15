<?php

use App\Models\BriInboundPaymentLog;
use App\Models\BriVirtualAccount;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatAdminKeuanganUntukVirtualAccount(): array
{
    Permission::firstOrCreate(['name' => 'pembayaran.virtual-account', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('pembayaran.virtual-account');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

function buatSiswaDenganVa(Lembaga $lembaga, string $nama, ?Kelas $kelas = null): array
{
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'nama_lengkap' => $nama,
        'kelas_id' => $kelas?->id,
        'status' => 'aktif',
    ]);
    $wallet = $siswa->wallet ?? Wallet::create(['siswa_id' => $siswa->id, 'balance' => 50000]);
    if ($siswa->wallet) {
        $siswa->wallet->update(['balance' => 50000]);
    }
    $va = BriVirtualAccount::create([
        'wallet_id' => $wallet->id,
        'va_type' => 'WALLET_PERMANENT',
        'va_number' => '8808' . str_pad((string) $siswa->id, 16, '0', STR_PAD_LEFT),
        'status' => 'PERMANENT',
    ]);

    return [$siswa, $wallet, $va];
}

it('denies access without pembayaran.virtual-account permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.virtual-account.index'))->assertForbidden();
});

it('lists only students who already have a virtual account', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Sudah Punya VA');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Belum Punya VA', 'status' => 'aktif']);

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index'));

    $response->assertOk();
    $response->assertSee('Sudah Punya VA');
    $response->assertDontSee('Belum Punya VA');
});

it('scopes the list to the admin own lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Anak Lembaga Sendiri');

    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    buatSiswaDenganVa($otherLembaga, 'Anak Lembaga Lain');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index'));

    $response->assertOk();
    $response->assertSee('Anak Lembaga Sendiri');
    $response->assertDontSee('Anak Lembaga Lain');
});

it('filters by search on siswa name', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Budi Santoso');
    buatSiswaDenganVa($lembaga, 'Siti Aminah');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index', ['search' => 'Budi']));

    $response->assertOk();
    $response->assertSee('Budi Santoso');
    $response->assertDontSee('Siti Aminah');
});

it('filters by kelas', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '6A']);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '6B']);
    buatSiswaDenganVa($lembaga, 'Anak Kelas A', $kelasA);
    buatSiswaDenganVa($lembaga, 'Anak Kelas B', $kelasB);

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index', ['kelas_id' => $kelasA->id]));

    $response->assertOk();
    $response->assertSee('Anak Kelas A');
    $response->assertDontSee('Anak Kelas B');
});

it('returns only the table partial for an AJAX request', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Anak Ajax');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertDontSee('<x-app-layout', false);
});

it('shows the inbound payment history for a student virtual account', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    [$siswa, , $va] = buatSiswaDenganVa($lembaga, 'Anak Riwayat');

    BriInboundPaymentLog::create([
        'payment_request_id' => 'PR-001',
        'va_number' => $va->va_number,
        'amount' => 75000,
    ]);

    $response = $this->actingAs($user)->get(route('admin.virtual-account.riwayat', $siswa));

    $response->assertOk();
    $response->assertSee('75.000');
    $response->assertSee('PR-001');
});

it('shows an empty state when a student virtual account has no payment history', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    [$siswa] = buatSiswaDenganVa($lembaga, 'Anak Belum Bayar');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.riwayat', $siswa));

    $response->assertOk();
    $response->assertSee('Belum ada pembayaran');
});

it('returns 404 when viewing riwayat for a student in another lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    [$siswaLain] = buatSiswaDenganVa($otherLembaga, 'Anak Lembaga Lain Riwayat');

    $this->actingAs($user)->get(route('admin.virtual-account.riwayat', $siswaLain))->assertNotFound();
});

it('calon-generate returns only active students without a virtual account', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Sudah Ada VA');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Belum Ada VA', 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Lulus', 'status' => 'lulus']);

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nama_lengkap');
    expect($names)->toContain('Belum Ada VA');
    expect($names)->not->toContain('Sudah Ada VA');
    expect($names)->not->toContain('Siswa Lulus');
});

it('calon-generate scopes to the admin own lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Calon Lembaga Sendiri', 'status' => 'aktif']);

    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Siswa::factory()->create(['lembaga_id' => $otherLembaga->id, 'nama_lengkap' => 'Calon Lembaga Lain', 'status' => 'aktif']);

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon'));

    $names = collect($response->json('data'))->pluck('nama_lengkap');
    expect($names)->toContain('Calon Lembaga Sendiri');
    expect($names)->not->toContain('Calon Lembaga Lain');
});

it('calon-generate filters by search and kelas', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '5A']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Ahmad Fauzi', 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Dewi Lestari', 'status' => 'aktif']);

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon', ['search' => 'Ahmad']));
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->toContain('Ahmad Fauzi');
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->not->toContain('Dewi Lestari');

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon', ['kelas_id' => $kelas->id]));
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->toContain('Ahmad Fauzi');
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->not->toContain('Dewi Lestari');
});

it('generates VA for all active students without one when mode is semua', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Calon A', 'status' => 'aktif']);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Calon B', 'status' => 'aktif']);
    [$siswaSudahAda] = buatSiswaDenganVa($lembaga, 'Sudah Ada VA Generate');

    $response = $this->actingAs($user)->post(route('admin.virtual-account.generate'), ['mode' => 'semua']);

    $response->assertRedirect(route('admin.virtual-account.index'));
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaA->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaB->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaSudahAda->id))->count())->toBe(1);
});

it('generates VA only for the selected students when mode is manual', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $siswaDipilih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    $siswaTidakDipilih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);

    $response = $this->actingAs($user)->post(route('admin.virtual-account.generate'), [
        'mode' => 'manual',
        'siswa_ids' => [$siswaDipilih->id],
    ]);

    $response->assertRedirect(route('admin.virtual-account.index'));
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaDipilih->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaTidakDipilih->id))->exists())->toBeFalse();
});

it('does not let manual mode generate VA for a student in another lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $otherLembaga->id, 'status' => 'aktif']);

    $this->actingAs($user)->post(route('admin.virtual-account.generate'), [
        'mode' => 'manual',
        'siswa_ids' => [$siswaLain->id],
    ]);

    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaLain->id))->exists())->toBeFalse();
});

it('does not fail the whole batch when one student has no wallet', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $siswaTanpaWallet = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    // delete wallet for siswaTanpaWallet if created
    Wallet::where('siswa_id', $siswaTanpaWallet->id)->delete();

    $siswaDenganWallet = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);

    $response = $this->actingAs($user)->post(route('admin.virtual-account.generate'), ['mode' => 'semua']);

    $response->assertRedirect(route('admin.virtual-account.index'));
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaDenganWallet->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaTanpaWallet->id))->exists())->toBeFalse();
});

it('exports the virtual account list as an excel file', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Anak Export');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('denies export without pembayaran.virtual-account permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.virtual-account.export'))->assertForbidden();
});
