<?php
// tests/Feature/Admin/AttendanceControllerTest.php

use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmCatat')) {
    function actingAsAdminSdmCatat(Lembaga $lembaga): User
    {
        foreach (['kehadiran-sdm.view', 'kehadiran-sdm.catat'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.catat']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('lets an admin_sdm record manual attendance for a guru in their own lembaga', function () {
    \Illuminate\Support\Carbon::setTestNow('2026-08-25 08:00:00'); // Tuesday (working day)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = actingAsAdminSdmCatat($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.store'), [
        'pegawai_tipe' => 'guru',
        'pegawai_id' => $guru->id,
        'arah' => 'masuk',
        'status' => 'hadir',
        'waktu' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('admin.kehadiran-sdm.index'));

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
    \Illuminate\Support\Carbon::setTestNow();
});

it('404s when recording attendance for a guru from a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $adminLembagaA = actingAsAdminSdmCatat($lembagaA);

    $this->actingAs($adminLembagaA)->post(route('admin.kehadiran-sdm.store'), [
        'pegawai_tipe' => 'guru',
        'pegawai_id' => $guruLembagaB->id,
        'arah' => 'masuk',
        'status' => 'hadir',
        'waktu' => now()->format('Y-m-d H:i:s'),
    ])->assertNotFound();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guruLembagaB->id)->exists())->toBeFalse();
});

it('rejects an admin without kehadiran-sdm.catat permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'arah' => 'masuk', 'status' => 'hadir', 'waktu' => now()->format('Y-m-d H:i:s'),
    ])->assertForbidden();
});
