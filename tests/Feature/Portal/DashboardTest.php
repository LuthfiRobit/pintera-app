<?php
// tests/Feature/Portal/DashboardTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatPendaftaranUntukAkun(AkunPendaftar $akun, string $nama = 'Ahmad Fauzan', ?string $status = null): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => $nama]);

    return Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => $status ?? 'menunggu_verifikasi',
    ]);
}

it('redirects an unverified akun away from the dashboard to the otp page', function () {
    $akun = AkunPendaftar::factory()->unverified()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.verifikasi-otp'));
});

it('shows only the pendaftaran linked to the logged-in akun', function () {
    $akunSaya = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akunSaya, 'Punya Saya');
    $akunLain = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akunLain, 'Punya Orang Lain');

    $response = $this->actingAs($akunSaya, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Punya Saya');
    $response->assertDontSee('Punya Orang Lain');
});

it('shows an empty state when no pendaftaran is linked', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))->assertOk();
});

it('allows downloading the bukti pendaftaran pdf for a pendaftaran the akun owns', function () {
    $akun = AkunPendaftar::factory()->create();
    $pendaftaran = buatPendaftaranUntukAkun($akun);

    $this->actingAs($akun, 'portal')
        ->get(route('portal.pendaftaran.bukti', $pendaftaran))
        ->assertOk();
});

it('404s when trying to download a pendaftaran belonging to a different akun', function () {
    $akunLain = AkunPendaftar::factory()->create();
    $pendaftaranOrangLain = buatPendaftaranUntukAkun($akunLain);
    $akunSaya = AkunPendaftar::factory()->create();

    $this->actingAs($akunSaya, 'portal')
        ->get(route('portal.pendaftaran.bukti', $pendaftaranOrangLain))
        ->assertNotFound();
});

it('redirects to the wizard when there is no pendaftaran and the session has a valid lembaga+jalur choice', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.wizard.data-diri'));
});

it('does not redirect to the wizard when the session points to a lembaga or jalur that no longer exists', function () {
    $akun = AkunPendaftar::factory()->create();
    session(['spmb_pilihan.lembaga_id' => 999999, 'spmb_pilihan.jalur_id' => 999999]);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk();
});

it('redirects to the wizard when the account has a decided pendaftaran but the session points to a different, not-yet-registered jalur', function () {
    [$lembaga, $tahunAjaran, $jalurA, $gelombang] = buatLembagaDenganGelombangBuka();
    $jalurB = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalurA->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => 'diterima',
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurB->id]);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.wizard.data-diri'));
});

it('clears the session and shows a status message instead of redirecting when the choice exactly matches a jalur the account already registered', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => 'diterima',
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id]);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('sudah terdaftar');
    expect(session('spmb_pilihan.lembaga_id'))->toBeNull();
    expect(session('spmb_pilihan.jalur_id'))->toBeNull();
});

it('clears the session and blocks a new jalur choice while an existing pendaftaran is still awaiting a decision', function () {
    [$lembaga, $tahunAjaran, $jalurA, $gelombang] = buatLembagaDenganGelombangBuka();
    $jalurB = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalurA->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => 'menunggu_verifikasi',
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurB->id]);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('menunggu keputusan');
    expect(session('spmb_pilihan.lembaga_id'))->toBeNull();
    expect(session('spmb_pilihan.jalur_id'))->toBeNull();
});

it('redirects a guest to login when accessing the dashboard', function () {
    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('login'));
});

it('shows the correct label for every pendaftaran status', function () {
    $akun = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akun, 'Calon Menunggu', 'menunggu_verifikasi');
    buatPendaftaranUntukAkun($akun, 'Calon Diterima', 'diterima');
    buatPendaftaranUntukAkun($akun, 'Calon Ditolak', 'ditolak');
    buatPendaftaranUntukAkun($akun, 'Calon Daftar Ulang', 'daftar_ulang');
    buatPendaftaranUntukAkun($akun, 'Calon Aktif', 'aktif');

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Menunggu Verifikasi');
    $response->assertSee('Diterima');
    $response->assertSee('Ditolak');
    $response->assertSee('Daftar Ulang');
    $response->assertSee('Aktif');
});

it('shows a Daftar Lagi button linking to the welcome page when the account has a pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akun);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Daftar Lagi')
        ->assertSee(route('spmb.welcome'), false);
});

it('shows an empty-state CTA linking to the welcome page when the account has no pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Pilih Lembaga & Jalur')
        ->assertSee(route('spmb.welcome'), false);
});

it('renders the authenticated navbar on the dashboard', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Riwayat')
        ->assertSee('Bantuan');
});
