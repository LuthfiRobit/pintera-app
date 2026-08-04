<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('accepts jenis_ptk=guru_bk and a kapasitas_kasus_aktif value when creating a guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('admin.guru.store'), [
        'nik' => '3201234567899999',
        'nip' => '198501012010011099',
        'nama' => 'Guru BK Baru',
        'email' => 'guru.bk@permata.sch.id',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY',
        'kapasitas_kasus_aktif' => '5',
    ])->assertRedirect(route('admin.guru.index'));

    $guru = Guru::where('nama', 'Guru BK Baru')->firstOrFail();
    expect($guru->jenis_ptk)->toBe('guru_bk');
    expect($guru->kapasitas_kasus_aktif)->toBe(5);
});

it('allows kapasitas_kasus_aktif to be left blank (unlimited)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('admin.guru.store'), [
        'nik' => '3201234567898888',
        'nip' => '198501012010011098',
        'nama' => 'Guru BK Tanpa Batas',
        'email' => 'guru.bk2@permata.sch.id',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY',
    ])->assertRedirect(route('admin.guru.index'));

    $guru = Guru::where('nama', 'Guru BK Tanpa Batas')->firstOrFail();
    expect($guru->kapasitas_kasus_aktif)->toBeNull();
});
