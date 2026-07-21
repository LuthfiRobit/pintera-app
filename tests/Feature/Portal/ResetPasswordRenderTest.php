<?php
// tests/Feature/Portal/ResetPasswordRenderTest.php

use App\Models\AkunPendaftar;
use Illuminate\Support\Facades\Password;

it('renders the reset-password page with a valid token', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test']);
    $token = Password::broker('akun_pendaftar')->createToken($akun);

    $response = $this->get(route('portal.password.reset', ['token' => $token]) . '?email=' . urlencode('ahmad@example.test'));

    $response->assertOk();
    $response->assertSee('Kata Sandi Baru');
});

it('resets the password with a valid token and a strong new password', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test', 'password' => 'OldPassword1']);
    $token = Password::broker('akun_pendaftar')->createToken($akun);

    $response = $this->post(route('portal.password.store'), [
        'token' => $token,
        'email' => 'ahmad@example.test',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertRedirect(route('portal.login'));
    $response->assertSessionHas('status');
    expect(\Illuminate\Support\Facades\Hash::check('NewPassword1', $akun->fresh()->password))->toBeTrue();
});

it('rejects a reset password that does not meet the strong-password rule', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test']);
    $token = Password::broker('akun_pendaftar')->createToken($akun);

    $response = $this->post(route('portal.password.store'), [
        'token' => $token,
        'email' => 'ahmad@example.test',
        'password' => 'lowercaseonly1',
        'password_confirmation' => 'lowercaseonly1',
    ]);

    $response->assertSessionHasErrors('password');
});
