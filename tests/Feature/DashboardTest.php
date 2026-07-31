<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('shows the guru placeholder dashboard to a user with only the guru role', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('guru');

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Dashboard Guru');
});

it('shows the siswa placeholder dashboard to a user with only the siswa role', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('siswa');

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Dashboard Siswa');
});

it('shows the yayasan dashboard with a lembaga switcher to a yayasan-scoped user', function () {
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera Switcher']);

    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('SD Pintera Switcher');
    $response->assertSee('switch_lembaga='.$lembaga->id, false);
});

it('shows the generic staff dashboard without a switcher to a lembaga-scoped user', function () {
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertDontSee('switch_lembaga', false);
});
