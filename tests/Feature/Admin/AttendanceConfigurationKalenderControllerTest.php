<?php
// tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmKalender')) {
    function actingAsAdminSdmKalender(Lembaga $lembaga): User
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

if (! function_exists('actingAsYayasanSuperAdminKalender')) {
    function actingAsYayasanSuperAdminKalender(Yayasan $yayasan): User
    {
        foreach (['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(Permission::all());

        $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('lets an admin_sdm update the weekly work-day pattern for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmKalender($lembaga);

    $this->actingAs($admin)->putJson(route('admin.kehadiran-sdm.kalender.hari-kerja'), [
        'hari_kerja' => [1, 2, 3, 4, 5],
    ])->assertOk()->assertJson(['data' => ['hari_libur_mingguan_sdm' => [0, 6]]]);
});

it('lets an admin_sdm create a lembaga-specific calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmKalender($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.kalender.entri.store'), [
        'nama' => 'Rapat Internal', 'tanggal' => '2026-09-01', 'tipe' => 'kerja',
    ])->assertRedirect();

    expect(KalenderKerjaSdm::where('lembaga_id', $lembaga->id)->where('nama', 'Rapat Internal')->exists())->toBeTrue();
});

it('rejects an admin_sdm (scope_level lembaga) trying to create a national calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmKalender($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.kalender.entri.store'), [
        'nama' => 'Cuti Bersama', 'tanggal' => '2026-09-01', 'tipe' => 'libur', 'is_nasional' => true,
    ])->assertForbidden();

    expect(KalenderKerjaSdm::whereNull('lembaga_id')->where('nama', 'Cuti Bersama')->exists())->toBeFalse();
});

it('lets a yayasan-scope actor create a national calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = actingAsYayasanSuperAdminKalender($yayasan);

    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($actor)->post(route('admin.kehadiran-sdm.kalender.entri.store'), [
        'nama' => 'Cuti Bersama', 'tanggal' => '2026-09-01', 'tipe' => 'libur', 'is_nasional' => true,
    ])->assertRedirect();

    expect(KalenderKerjaSdm::withoutGlobalScopes()->whereNull('lembaga_id')->where('nama', 'Cuti Bersama')->exists())->toBeTrue();
});

it('lists academic national entries not yet copied, and excludes ones already copied', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = actingAsYayasanSuperAdminKalender($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-12-25', 'nama' => 'Hari Natal', 'tipe' => 'libur']);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2026-12-25', 'nama' => 'Hari Natal', 'tipe' => 'libur']);

    $response = $this->actingAs($actor)->getJson(route('admin.kehadiran-sdm.kalender.salin-tersedia'));

    $response->assertOk();
    $items = $response->json('items');
    expect(collect($items)->pluck('nama')->all())->toBe(['Hari Kemerdekaan RI']);
});

it('copies selected academic entries into SDM calendar entries via the salin endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = actingAsYayasanSuperAdminKalender($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $this->actingAs($actor)->post(route('admin.kehadiran-sdm.kalender.salin'), [
        'kalender_akademik_ids' => [$entriAkademik->id],
    ])->assertRedirect();

    expect(KalenderKerjaSdm::withoutGlobalScopes()->where('yayasan_id', $yayasan->id)->where('nama', 'Hari Kemerdekaan RI')->exists())->toBeTrue();
});
