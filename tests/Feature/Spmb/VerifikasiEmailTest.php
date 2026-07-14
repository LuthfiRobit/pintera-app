<?php

use App\Models\VerifikasiEmailOtp;
use App\Services\PendaftaranWizardSession;
use Illuminate\Support\Facades\Mail;

it('sends an otp and advances to the otp-input step', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'wali@example.test'])
        ->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/verifikasi-otp");

    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->exists())->toBeTrue();
});

it('rejects an invalid email format without sending an otp', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'bukan-email'])
        ->assertSessionHasErrors('email');

    expect(VerifikasiEmailOtp::count())->toBe(0);
});

it('verifies a correct otp and stores email_pendaftaran in the wizard session', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'wali@example.test']);
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/verifikasi-otp", ['kode_otp' => $kode])
        ->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri");

    $session = (new PendaftaranWizardSession)->get($lembaga, $jalur);
    expect($session['email_pendaftaran'])->toBe('wali@example.test');
});

it('rejects a wrong otp and stays on the otp step', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'wali@example.test']);

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/verifikasi-otp", ['kode_otp' => '000000'])
        ->assertSessionHasErrors('kode_otp');

    $session = (new PendaftaranWizardSession)->get($lembaga, $jalur);
    expect($session['email_pendaftaran'] ?? null)->toBeNull();
});
