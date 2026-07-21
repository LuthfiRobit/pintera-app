<?php
// tests/Feature/Portal/ResendOtpTest.php

use App\Models\AkunPendaftar;
use App\Models\VerifikasiEmailOtp;

it('sends a new otp and deletes the old unverified one when resend is requested', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    $kodeLama = VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first()->kode_otp;

    $response = $this->withSession(['portal_register_email_pending' => 'ahmad@example.test'])
        ->post(route('portal.verifikasi-otp.kirim-ulang'));

    $response->assertRedirect(route('portal.verifikasi-otp'));
    $otpBaru = VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first();
    expect($otpBaru->kode_otp)->not->toBe($kodeLama);
    expect(VerifikasiEmailOtp::where('email', 'ahmad@example.test')->whereNull('verified_at')->count())->toBe(1);
});

it('does nothing and redirects if there is no pending email in session', function () {
    $response = $this->post(route('portal.verifikasi-otp.kirim-ulang'));

    $response->assertRedirect(route('portal.verifikasi-otp'));
    expect(VerifikasiEmailOtp::count())->toBe(0);
});

it('shows a countdown-driven resend affordance on the otp page', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');

    $response = $this->withSession(['portal_register_email_pending' => 'ahmad@example.test'])
        ->get(route('portal.verifikasi-otp'));

    $response->assertOk();
    $response->assertSee('ahmad@example.test');
    $response->assertSee('kirim-ulang', false);
});

it('computes a countdown that decreases as time passes, not increases', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');

    $otp = VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first();
    $otp->created_at = now()->subSeconds(20);
    $otp->save();

    $response = $this->withSession(['portal_register_email_pending' => 'ahmad@example.test'])
        ->get(route('portal.verifikasi-otp'));

    $response->assertOk();
    $response->assertViewHas('detikTersisa', function ($detikTersisa) {
        return $detikTersisa > 0 && $detikTersisa <= 40;
    });
});
