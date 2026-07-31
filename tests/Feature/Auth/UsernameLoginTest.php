<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Hash;

it('logs in with a username instead of an email', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create([
        'lembaga_id' => $lembaga->id,
        'email' => null,
        'username' => 'SMPPRM-2026001',
        'password' => Hash::make('2026001'),
    ]);
    $user->assignRole('siswa');

    $response = $this->post('/login', ['email' => 'SMPPRM-2026001', 'password' => '2026001']);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

it('still logs in with an email for accounts that have one', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password for a username-based account', function () {
    $user = User::factory()->create([
        'email' => null,
        'username' => 'SMPPRM-2026002',
        'password' => Hash::make('2026002'),
    ]);

    $response = $this->post('/login', ['email' => 'SMPPRM-2026002', 'password' => 'salah']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
