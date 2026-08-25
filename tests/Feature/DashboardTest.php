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

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('SD Pintera Switcher');
    $response->assertSee('switch_lembaga='.$lembaga->id, false);
});

it('does not show a lembaga belonging to another yayasan on the yayasan dashboard', function () {
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id, 'nama' => 'SMP Milik Saya']);
    Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id, 'nama' => 'SMA Yayasan Lain']);

    $user = User::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get('/dashboard');

    // Regression test for the DashboardController cross-yayasan stats leak: Lembaga::all()
    // was previously unfiltered, so a yayasan admin's landing dashboard showed institution
    // names, SPMB/keuangan aggregates, and system-wide counts from every other yayasan too.
    $response->assertOk();
    $response->assertSee('SMP Milik Saya');
    $response->assertDontSee('SMA Yayasan Lain');
});

it('shows the platform dashboard with cross-yayasan aggregates to a platform_super_admin', function () {
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);

    $yayasanA = Yayasan::factory()->create(['nama' => 'Yayasan Alpha']);
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $yayasanB = Yayasan::factory()->create(['nama' => 'Yayasan Beta']);
    Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $guru = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $guru->assignRole('guru');

    $admin = User::factory()->create();
    $admin->assignRole('platform_super_admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertViewIs('admin.dashboard.platform');
    $response->assertSee('Yayasan Alpha');
    $response->assertSee('Yayasan Beta');
    $response->assertViewHas('stats', function ($stats) {
        return $stats['yayasan'] === 2 && $stats['lembaga'] === 2;
    });
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
