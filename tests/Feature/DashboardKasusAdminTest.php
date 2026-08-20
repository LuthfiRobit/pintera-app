<?php
// tests/Feature/DashboardKasusAdminTest.php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('shows an admin_akademik all kasus in their lembaga, with eskalasi first', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    foreach (['kasus.view', 'kasus.triase'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.triase']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'status' => StatusKasus::Berjalan, 'kategori_masalah' => 'Kasus Berjalan', 'deskripsi' => 'x',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'status' => StatusKasus::Eskalasi, 'kategori_masalah' => 'Kasus Eskalasi', 'deskripsi' => 'x',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()->assertSee('Kasus Berjalan')->assertSee('Kasus Eskalasi');
    $content = $response->getContent();
    expect(strpos($content, 'Kasus Eskalasi'))->toBeLessThan(strpos($content, 'Kasus Berjalan'));
});

it('does not show the kasus panel to an admin without kasus.triase', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()->assertDontSee('Kasus Pendampingan');
});
