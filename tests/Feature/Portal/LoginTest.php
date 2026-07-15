<?php
// tests/Feature/Portal/LoginTest.php

use App\Models\AkunPendaftar;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs a verified akun in with correct credentials', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response = $this->post(route('portal.login'), ['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response->assertRedirect(route('portal.dashboard'));
    $this->assertAuthenticatedAs($akun, 'portal');
});

it('rejects an incorrect password', function () {
    AkunPendaftar::factory()->create(['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response = $this->post(route('portal.login'), ['email' => 'ahmad@example.test', 'password' => 'salah']);

    $response->assertSessionHasErrors();
    $this->assertGuest('portal');
});

it('blocks login for an unverified akun and resends the otp', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response = $this->post(route('portal.login'), ['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response->assertRedirect(route('portal.verifikasi-otp'));
    $this->assertGuest('portal');
    expect(\App\Models\VerifikasiEmailOtp::where('email', 'ahmad@example.test')->exists())->toBeTrue();
});

it('logs the akun out', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->post(route('portal.logout'));

    $this->assertGuest('portal');
});

it('does not let a staff user account authenticate against the portal guard, and vice versa', function () {
    (new RolePermissionSeeder)->run();
    $staff = User::factory()->create(['password' => 'password123']);
    $akun = AkunPendaftar::factory()->create(['password' => 'password123']);

    expect(\Illuminate\Support\Facades\Auth::guard('portal')->attempt(['email' => $staff->email, 'password' => 'password123']))->toBeFalse();
    expect(\Illuminate\Support\Facades\Auth::guard('web')->attempt(['email' => $akun->email, 'password' => 'password123']))->toBeFalse();
});
