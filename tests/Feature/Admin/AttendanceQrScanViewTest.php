<?php
// tests/Feature/Admin/AttendanceQrScanViewTest.php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('renders the scan page with both camera and manual mode toggles', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.scan.index'));

    $response->assertOk()
        ->assertSee('Scan Kamera')
        ->assertSee('Input Manual')
        ->assertSee('qr-camera-reader', false)
        ->assertSee('qrCameraScanner', false);
});

it('returns 422 when a yayasan-scope user has not picked an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_scan_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.scan.index'));

    $response->assertStatus(422);
});

it('renders the scan page normally once a yayasan-scope user picks an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_scan_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    session(['active_lembaga_id' => $lembaga->id]);
    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.scan.index'));

    $response->assertOk();
});
