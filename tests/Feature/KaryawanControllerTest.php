<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'karyawan.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'karyawan.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'karyawan.edit', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'pegawai_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
});

it('updates Karyawan identity through PersonService', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'name' => 'Nama Lama']);
    $person = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'user_id' => $user->id,
        'nama_lengkap' => 'Nama Lama',
        'nik' => '6666666666666668',
        'no_hp' => '081233334444',
        'email' => 'lama@example.test',
    ]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create([
        'lembaga_id' => $lembaga->id,
        'yayasan_id' => $yayasan->id,
        'person_id' => $person->id,
        'user_id' => $user->id,
        'jenis_karyawan_id' => $jenisKaryawan->id,
    ]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['karyawan.edit', 'karyawan.view']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->put(route('admin.karyawan.update', $karyawan), [
        'nama' => 'Nama Baru Karyawan',
        'email' => 'baru@example.test',
        'no_hp' => '081299998888',
        'jenis_karyawan_id' => $jenisKaryawan->id,
    ]);

    $response->assertRedirect(route('admin.karyawan.index'));
    expect($karyawan->fresh()->nama)->toBe('Nama Baru Karyawan');
    expect($person->fresh()->nama_lengkap)->toBe('Nama Baru Karyawan');
    expect($person->fresh()->email)->toBe('baru@example.test');
    expect($person->fresh()->no_hp)->toBe('081299998888');
    expect($user->fresh()->name)->toBe('Nama Baru Karyawan');
});
