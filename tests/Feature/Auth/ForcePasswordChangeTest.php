<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Hash;

it('redirects a must_change_password account to the force-password page after login', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create([
        'lembaga_id' => $lembaga->id,
        'email' => null,
        'username' => 'SMPPRM-2026001',
        'password' => Hash::make('2026001'),
        'must_change_password' => true,
    ]);
    $user->assignRole('siswa');

    $this->post('/login', ['email' => 'SMPPRM-2026001', 'password' => '2026001']);

    $this->get(route('dashboard'))->assertRedirect(route('password.force.edit'));
});

it('lets the user change password and then access the app normally', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create([
        'lembaga_id' => $lembaga->id,
        'password' => Hash::make('password'),
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->put(route('password.force.update'), [
            'password' => 'PasswordBaru123!',
            'password_confirmation' => 'PasswordBaru123!',
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('PasswordBaru123!', $user->fresh()->password))->toBeTrue();

    $this->get(route('dashboard'))->assertOk();
});

it('does not block a normal account that does not need a password change', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'must_change_password' => false]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('still allows logout even while must_change_password is true', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
});
