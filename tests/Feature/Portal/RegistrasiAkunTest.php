<?php
// tests/Feature/Portal/RegistrasiAkunTest.php

use App\Mail\KodeOtpMail;
use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers a new unverified akun and sends an otp email', function () {
    Mail::fake();

    $response = $this->post(route('portal.register'), [
        'nama' => 'Ahmad Fauzan',
        'email' => 'ahmad@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('portal.verifikasi-otp'));
    $akun = AkunPendaftar::where('email', 'ahmad@example.test')->first();
    expect($akun)->not->toBeNull();
    expect($akun->email_verified_at)->toBeNull();
    Mail::assertSent(KodeOtpMail::class);
});

it('rejects registration with a duplicate email', function () {
    AkunPendaftar::factory()->create(['email' => 'sudah@example.test']);

    $response = $this->post(route('portal.register'), [
        'nama' => 'Duplikat',
        'email' => 'sudah@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
});

it('verifies the correct otp, logs the akun in, and auto-links an existing pendaftaran with the same email', function () {
    $akun = AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'email_pendaftaran' => 'ahmad@example.test',
    ]);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    $kode = \App\Models\VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first()->kode_otp;
    session(['portal_register_email_pending' => 'ahmad@example.test']);

    $response = $this->post(route('portal.verifikasi-otp.store'), ['kode_otp' => $kode]);

    $response->assertRedirect(route('portal.dashboard'));
    $this->assertAuthenticatedAs($akun->fresh(), 'portal');
    expect($akun->fresh()->email_verified_at)->not->toBeNull();
    expect($pendaftaran->fresh()->akun_pendaftar_id)->toBe($akun->id);
});

it('does not auto-link a pendaftaran with a different email', function () {
    $akun = AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaranLain = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'email_pendaftaran' => 'orang.lain@example.test',
    ]);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    $kode = \App\Models\VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first()->kode_otp;
    session(['portal_register_email_pending' => 'ahmad@example.test']);

    $this->post(route('portal.verifikasi-otp.store'), ['kode_otp' => $kode]);

    expect($pendaftaranLain->fresh()->akun_pendaftar_id)->toBeNull();
});

it('rejects a wrong otp code', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    session(['portal_register_email_pending' => 'ahmad@example.test']);

    $response = $this->post(route('portal.verifikasi-otp.store'), ['kode_otp' => '000000']);

    $response->assertSessionHasErrors('kode_otp');
    $this->assertGuest('portal');
});
