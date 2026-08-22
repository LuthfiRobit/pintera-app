<?php
// tests/Feature/Admin/AttendanceConfigurationControllerTest.php

use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdm')) {
    function actingAsAdminSdm(Lembaga $lembaga): User
    {
        foreach (['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('lets an admin_sdm enable the qr method for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.konfigurasi.metode'), [
        'method' => 'qr', 'is_enabled' => '1',
    ])->assertRedirect();

    expect(AttendanceMethodConfiguration::where('lembaga_id', $lembaga->id)->where('method', 'qr')->first()?->is_enabled)->toBeTrue();
});

it('lets an admin_sdm add an attendance point for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.titik.store'), ['nama' => 'Gerbang Utama'])
        ->assertRedirect();

    expect(AttendancePoint::where('lembaga_id', $lembaga->id)->where('nama', 'Gerbang Utama')->exists())->toBeTrue();
});

it('rejects an admin without kehadiran-sdm.kelola-konfigurasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.titik.store'), ['nama' => 'Gerbang Utama'])
        ->assertForbidden();
});

it('shows the yayasan-level default method configuration (lembaga_id null) to a lembaga-scoped admin_sdm', function () {
    // Regression guard: TenantScope forces `lembaga_id = actingUser->lembaga_id` as a
    // top-level AND for a scope_level:lembaga actor, so a naive query would never surface
    // the yayasan default row (lembaga_id IS NULL) no matter what OR clause is added on top.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    AttendanceMethodConfiguration::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'method' => 'qr', 'is_enabled' => true,
    ]);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertViewHas('konfigurasi', function ($konfigurasi) {
            return $konfigurasi->contains(fn ($row) => $row->method->value === 'qr' && $row->lembaga_id === null && $row->is_enabled === true);
        });
});
