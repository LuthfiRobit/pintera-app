<?php
// tests/Feature/Portal/DashboardTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatPendaftaranUntukAkun(AkunPendaftar $akun, string $nama = 'Ahmad Fauzan'): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => $nama]);

    return Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
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
