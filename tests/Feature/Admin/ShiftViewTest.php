<?php
// tests/Feature/Admin/ShiftViewTest.php

use App\Domains\Sdm\Models\JenisShift;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('renders the konfigurasi page with the Shift Bergilir tab and existing jenis_shift rows', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kehadiran-sdm.view');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertSee('Shift Bergilir')
        ->assertSee('Shift Malam');
});
