<?php

use App\Domains\Identity\Models\Person;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'siswa.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'siswa.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'siswa.edit', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
});

it('creates a Siswa with Person and User linked via SiswaController@store', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SD01']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.create', 'siswa.view', 'siswa.edit']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->post(route('admin.siswa.store'), [
        'nama_lengkap' => 'Siswa Baru',
        'nis' => '1001',
        'nisn' => '0012345678',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-05-10',
        'agama' => 'Islam',
        'kelas_id' => $kelas->id,
    ]);

    $response->assertRedirect(route('admin.siswa.index'));
    $siswa = Siswa::where('nis', '1001')->first();
    expect($siswa)->not->toBeNull();
    expect($siswa->person_id)->not->toBeNull();
    expect($siswa->nama_lengkap)->toBe('Siswa Baru');
    expect($siswa->jenis_kelamin)->toBe('L');

    $person = Person::withoutGlobalScopes()->find($siswa->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->nama_lengkap)->toBe('Siswa Baru');
    expect($person->user_id)->toBe($siswa->user_id);
});

it('updates a Siswa identity via SiswaController@update', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SD01']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'name' => 'Nama Lama', 'username' => 'SD01-1002']);
    $person = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'user_id' => $user->id,
        'nama_lengkap' => 'Nama Lama',
        'jenis_kelamin' => 'L',
    ]);
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'person_id' => $person->id,
        'user_id' => $user->id,
        'nis' => '1002',
    ]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.edit', 'siswa.view']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->put(route('admin.siswa.update', $siswa), [
        'nama_lengkap' => 'Nama Baru Siswa',
        'nis' => '1002',
        'nisn' => '0012345679',
        'jenis_kelamin' => 'L',
    ]);

    $response->assertRedirect(route('admin.siswa.index'));
    expect($siswa->fresh()->nama_lengkap)->toBe('Nama Baru Siswa');
    expect($person->fresh()->nama_lengkap)->toBe('Nama Baru Siswa');
    expect($user->fresh()->name)->toBe('Nama Baru Siswa');
});
