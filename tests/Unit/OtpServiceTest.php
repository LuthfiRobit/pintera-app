<?php

use App\Mail\KodeOtpMail;
use App\Models\VerifikasiEmailOtp;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates and emails a 6-digit otp code', function () {
    Mail::fake();

    (new OtpService())->kirim('wali@example.test');

    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->count())->toBe(1);
    $otp = VerifikasiEmailOtp::where('email', 'wali@example.test')->first();
    expect($otp->kode_otp)->toMatch('/^\d{6}$/');
    expect($otp->expires_at)->toBeGreaterThan(now());

    Mail::assertSent(KodeOtpMail::class, function (KodeOtpMail $mail) use ($otp) {
        return $mail->hasTo('wali@example.test') && $mail->kodeOtp === $otp->kode_otp;
    });
});

it('clears prior unverified codes for the same email before issuing a new one', function () {
    Mail::fake();
    $service = new OtpService();

    $service->kirim('wali@example.test');
    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->count())->toBe(1);

    $service->kirim('wali@example.test');
    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->count())->toBe(1);
});

it('verifies a correct, unexpired, unused code', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    expect($service->verifikasi('wali@example.test', $kode))->toBeTrue();
    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->verified_at)->not->toBeNull();
});

it('rejects a wrong code', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');

    expect($service->verifikasi('wali@example.test', '000000'))->toBeFalse();
});

it('rejects an expired code', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');
    VerifikasiEmailOtp::where('email', 'wali@example.test')->update(['expires_at' => now()->subMinute()]);
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    expect($service->verifikasi('wali@example.test', $kode))->toBeFalse();
});

it('rejects a code that has already been used once', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    expect($service->verifikasi('wali@example.test', $kode))->toBeTrue();
    expect($service->verifikasi('wali@example.test', $kode))->toBeFalse();
});
