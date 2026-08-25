<?php
// tests/Feature/Admin/KasusPendampinganSidebarTest.php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('shows Log Akses Klinis and Kasus Terhapus links to a user with kasus.lihat-log-akses', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['kasus.view', 'kasus.lihat-log-akses'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.lihat-log-akses']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.kasus.index'));

    $response->assertOk();
    $response->assertSee('Log Akses Klinis');
    $response->assertSee('Kasus Terhapus');
    $response->assertSee(route('admin.kasus.log-akses'));
    $response->assertSee(route('admin.kasus.terhapus'));
});

it('hides Log Akses Klinis and Kasus Terhapus links from a user without the permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kasus.view');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('kasus.index'));

    $response->assertOk();
    $response->assertDontSee('Log Akses Klinis');
    $response->assertDontSee('Kasus Terhapus');
});
