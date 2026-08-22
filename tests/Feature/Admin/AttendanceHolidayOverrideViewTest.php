<?php
// tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php

use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('shows a holiday error message and allows retry with override checkbox checked', function () {
    foreach (['kehadiran-sdm.view', 'kehadiran-sdm.catat'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.catat']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $payload = [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'arah' => 'masuk',
        'status' => 'hadir', 'waktu' => '2026-08-23 07:00:00', // Sunday
    ];

    $this->actingAs($admin)->from(route('admin.kehadiran-sdm.create'))->post(route('admin.kehadiran-sdm.store'), $payload)
        ->assertRedirect(route('admin.kehadiran-sdm.create'))
        ->assertSessionHasErrors('tanggal');

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.store'), $payload + ['override_hari_libur' => '1'])
        ->assertRedirect(route('admin.kehadiran-sdm.index'));

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});
