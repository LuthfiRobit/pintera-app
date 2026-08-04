<?php
// tests/Feature/Admin/KaryawanCrudTest.php

use App\Models\Karyawan;
use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function actingAsKaryawanManager(Lembaga $lembaga): User
{
    foreach (['karyawan.view', 'karyawan.create', 'karyawan.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['karyawan.view', 'karyawan.create', 'karyawan.edit']);
    Role::firstOrCreate(['name' => 'karyawan_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'karyawan_pool', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

function actingAsYayasanSuperAdmin(): User
{
    $admin = User::factory()->create(['lembaga_id' => null]);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    foreach (['karyawan.view', 'karyawan.create', 'karyawan.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role->givePermissionTo(['karyawan.view', 'karyawan.create', 'karyawan.edit']);
    Role::firstOrCreate(['name' => 'karyawan_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'karyawan_pool', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $admin->assignRole($role);

    return $admin;
}

it('denies access to a user without karyawan.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.karyawan.index'))->assertForbidden();
});

it('lets a lembaga admin create a dedicated karyawan scoped to their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKaryawanManager($lembaga);
    $jenis = JenisKaryawanMaster::factory()->create();

    $this->actingAs($manager)->post(route('admin.karyawan.store'), [
        'nik' => '3201234567891234',
        'nama' => 'Konselor Lembaga',
        'email' => 'konselor@permata.sch.id',
        'no_hp' => '081234567890',
        'jenis_karyawan_id' => $jenis->id,
    ])->assertRedirect(route('admin.karyawan.index'));

    $karyawan = Karyawan::where('nama', 'Konselor Lembaga')->firstOrFail();
    expect($karyawan->lembaga_id)->toBe($lembaga->id);
    expect($karyawan->yayasan_id)->toBe($yayasan->id);
    expect($karyawan->user->hasRole('karyawan_lembaga'))->toBeTrue();
    expect(Hash::check('3201234567891234', $karyawan->user->password))->toBeTrue();
});

it('rejects a lembaga admin trying to create a pool karyawan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKaryawanManager($lembaga);
    $jenis = JenisKaryawanMaster::factory()->create();

    $this->actingAs($manager)->post(route('admin.karyawan.store'), [
        'nik' => '3201234567895555',
        'nama' => 'Percobaan Pool',
        'email' => 'coba@permata.sch.id',
        'jenis_karyawan_id' => $jenis->id,
        'is_pool' => '1',
        'yayasan_id' => $yayasan->id,
    ])->assertForbidden();

    expect(Karyawan::where('nama', 'Percobaan Pool')->exists())->toBeFalse();
});

it('lets yayasan_super_admin create a pool karyawan', function () {
    $admin = actingAsYayasanSuperAdmin();
    $yayasan = Yayasan::factory()->create();
    $jenis = JenisKaryawanMaster::factory()->create();

    $this->actingAs($admin)->post(route('admin.karyawan.store'), [
        'nik' => '3201234567896666',
        'nama' => 'Psikolog Pool',
        'email' => 'psikolog@yayasan.test',
        'jenis_karyawan_id' => $jenis->id,
        'is_pool' => '1',
        'yayasan_id' => $yayasan->id,
    ])->assertRedirect(route('admin.karyawan.index'));

    $karyawan = Karyawan::where('nama', 'Psikolog Pool')->firstOrFail();
    expect($karyawan->lembaga_id)->toBeNull();
    expect($karyawan->yayasan_id)->toBe($yayasan->id);
    expect($karyawan->user->hasRole('karyawan_pool'))->toBeTrue();
});

it('updates a karyawan profile without touching nik or lembaga_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKaryawanManager($lembaga);
    $jenisA = JenisKaryawanMaster::factory()->create();
    $jenisB = JenisKaryawanMaster::factory()->create();

    $this->actingAs($manager)->post(route('admin.karyawan.store'), [
        'nik' => '3201234567897777', 'nama' => 'Nama Lama', 'email' => 'lama@permata.sch.id', 'jenis_karyawan_id' => $jenisA->id,
    ])->assertRedirect();
    $karyawan = Karyawan::where('nik_hash', hash('sha256', '3201234567897777'))->firstOrFail();

    $this->actingAs($manager)->put(route('admin.karyawan.update', $karyawan), [
        'nama' => 'Nama Baru', 'email' => 'baru@permata.sch.id', 'jenis_karyawan_id' => $jenisB->id,
    ])->assertRedirect(route('admin.karyawan.index'));

    $karyawan->refresh();
    expect($karyawan->nama)->toBe('Nama Baru');
    expect($karyawan->jenis_karyawan_id)->toBe($jenisB->id);
    expect($karyawan->nik)->toBe('3201234567897777');
    expect($karyawan->lembaga_id)->toBe($lembaga->id);
});

it('toggles a karyawan status_aktif and the linked user is_active together', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKaryawanManager($lembaga);
    $jenis = JenisKaryawanMaster::factory()->create();

    $this->actingAs($manager)->post(route('admin.karyawan.store'), [
        'nik' => '3201234567898888', 'nama' => 'Toggle Status', 'jenis_karyawan_id' => $jenis->id,
    ])->assertRedirect();
    $karyawan = Karyawan::where('nik_hash', hash('sha256', '3201234567898888'))->firstOrFail();

    $this->actingAs($manager)->patch(route('admin.karyawan.update-status', $karyawan), [
        'status_aktif' => 'non_aktif',
    ])->assertRedirect(route('admin.karyawan.index'));

    $karyawan->refresh();
    expect($karyawan->status_aktif)->toBe('non_aktif');
    expect($karyawan->user->is_active)->toBeFalse();
});
