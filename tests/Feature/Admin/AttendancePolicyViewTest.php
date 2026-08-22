<?php
// tests/Feature/Admin/AttendancePolicyViewTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('renders the konfigurasi page with the Attendance Policy tab and existing policy rows', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kehadiran-sdm.view');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15]);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertSee('Attendance Policy')
        ->assertSee('Guru Kelas')
        ->assertSee('07:00');
});
