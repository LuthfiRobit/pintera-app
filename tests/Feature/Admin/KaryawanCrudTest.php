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
    $admin->update(['yayasan_id' => $yayasan->id]);
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

it('rejects creating a karyawan whose NIK is already registered to a non-karyawan account', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKaryawanManager($lembaga);
    $jenis = JenisKaryawanMaster::factory()->create();

    $existingUser = User::factory()->create(['username' => '3201234567899999']);
    $usersBefore = User::withoutGlobalScopes()->count();

    $this->actingAs($manager)->post(route('admin.karyawan.store'), [
        'nik' => '3201234567899999',
        'nama' => 'Percobaan Duplikat',
        'jenis_karyawan_id' => $jenis->id,
    ])->assertSessionHasErrors('nik');

    expect(User::withoutGlobalScopes()->count())->toBe($usersBefore);
    expect(Karyawan::where('nama', 'Percobaan Duplikat')->exists())->toBeFalse();
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

it('shows a lembaga-scoped admin their own dedicated karyawan plus their yayasan pool karyawan, but not other lembaga/yayasan data', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaAOther = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $manager = actingAsKaryawanManager($lembagaA);
    $jenis = JenisKaryawanMaster::factory()->create();

    $dedicatedOwn = Karyawan::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaA->id])->id,
        'yayasan_id' => $yayasanA->id,
        'lembaga_id' => $lembagaA->id,
        'jenis_karyawan_id' => $jenis->id,
        'nama' => 'Karyawan Lembaga Sendiri',
        'nik' => '3201234567900001',
        'status_aktif' => 'aktif',
    ]);

    $poolSameYayasan = Karyawan::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => null])->id,
        'yayasan_id' => $yayasanA->id,
        'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id,
        'nama' => 'Karyawan Pool Yayasan Sendiri',
        'nik' => '3201234567900002',
        'status_aktif' => 'aktif',
    ]);

    $dedicatedOtherLembaga = Karyawan::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaAOther->id])->id,
        'yayasan_id' => $yayasanA->id,
        'lembaga_id' => $lembagaAOther->id,
        'jenis_karyawan_id' => $jenis->id,
        'nama' => 'Karyawan Lembaga Lain',
        'nik' => '3201234567900003',
        'status_aktif' => 'aktif',
    ]);

    $poolOtherYayasan = Karyawan::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => null])->id,
        'yayasan_id' => $yayasanB->id,
        'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id,
        'nama' => 'Karyawan Pool Yayasan Lain',
        'nik' => '3201234567900004',
        'status_aktif' => 'aktif',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.karyawan.index'));

    $response->assertOk();
    $response->assertSee('Karyawan Lembaga Sendiri');
    $response->assertSee('Karyawan Pool Yayasan Sendiri');
    $response->assertDontSee('Karyawan Lembaga Lain');
    $response->assertDontSee('Karyawan Pool Yayasan Lain');
});
