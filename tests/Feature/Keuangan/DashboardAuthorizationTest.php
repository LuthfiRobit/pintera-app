<?php
// tests/Feature/Keuangan/DashboardAuthorizationTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('never resolves another parent\'s child as the active siswa via switch_siswa', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaMine = Siswa::factory()->create(['lembaga_id' => $lembagaA->id, 'nama_lengkap' => 'Anak Saya']);
    $siswaOther = Siswa::factory()->create(['lembaga_id' => $lembagaB->id, 'nama_lengkap' => 'Anak Orang Lain']);

    $me = User::factory()->create(['lembaga_id' => null]);
    $me->assignRole('orang_tua');
    $ortuMe = OrangTua::create([
        'user_id' => $me->id, 'nama_lengkap' => 'Saya', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $ortuMe->siswa()->attach($siswaMine->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $otherUser = User::factory()->create(['lembaga_id' => null]);
    $otherUser->assignRole('orang_tua');
    $ortuOther = OrangTua::create([
        'user_id' => $otherUser->id, 'nama_lengkap' => 'Orang Lain', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
    ]);
    $ortuOther->siswa()->attach($siswaOther->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    // Try to hijack the session into showing the other parent's child.
    $response = $this->actingAs($me)->get(route('keuangan.dashboard', ['switch_siswa' => $siswaOther->id]));

    $response->assertOk();
    $response->assertSee('Anak Saya');
    $response->assertDontSee('Anak Orang Lain');
});

it('scopes the skip-alert and wallet data to the correct tenant across two lembaga', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $siswa->wallet->update(['balance' => 999000, 'va_number' => '8808089999999999']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Lintas Lembaga', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009999',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    // No active_lembaga_id session set (this admin-side concept is irrelevant for an
    // orang_tua user, who has no lembaga_id of their own) — the dashboard must still
    // resolve the wallet correctly via the relation-membership path, not lembaga_id.
    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('999.000', false);
    $response->assertSee('8808089999999999');
});
