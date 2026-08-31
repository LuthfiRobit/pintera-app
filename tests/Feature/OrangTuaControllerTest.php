<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'orang-tua.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'orang-tua.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'orang-tua.edit', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
});

it('creates an OrangTua with PersonService integration via OrangTuaController@store', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['orang-tua.create', 'orang-tua.view', 'orang-tua.edit']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->post(route('admin.orang-tua.store'), [
        'nama_lengkap' => 'Bapak Budi',
        'nik' => '7777777777777778',
        'no_hp' => '081277778888',
        'email' => 'budi@example.test',
        'alamat' => 'Jl. Mawar No. 5',
        'pekerjaan' => 'PNS',
    ]);

    $response->assertRedirect(route('admin.orang-tua.index'));
    $ortu = OrangTua::latest('id')->first();
    expect($ortu)->not->toBeNull();
    expect($ortu->person_id)->not->toBeNull();
    expect($ortu->nama_lengkap)->toBe('Bapak Budi');
    expect($ortu->nik)->toBe('7777777777777778');
    expect($ortu->alamat)->toBe('Jl. Mawar No. 5');
});

it('updates OrangTua identity via OrangTuaController@update', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['name' => 'Nama Lama']);
    $person = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'user_id' => $user->id,
        'nama_lengkap' => 'Nama Lama',
        'nik' => '7777777777777779',
        'no_hp' => '081211112222',
        'email' => 'lama@example.test',
        'alamat_jalan' => 'Alamat Lama',
    ]);
    $ortu = OrangTua::factory()->create([
        'person_id' => $person->id,
        'user_id' => $user->id,
        'pekerjaan' => 'Buruh',
    ]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['orang-tua.edit', 'orang-tua.view']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->put(route('admin.orang-tua.update', $ortu), [
        'nama_lengkap' => 'Nama Baru Ortu',
        'no_hp' => '081299990000',
        'email' => 'baru@example.test',
        'alamat' => 'Alamat Baru',
        'pekerjaan' => 'Wiraswasta',
    ]);

    $response->assertRedirect(route('admin.orang-tua.index'));
    expect($ortu->fresh()->nama_lengkap)->toBe('Nama Baru Ortu');
    expect($ortu->fresh()->alamat)->toBe('Alamat Baru');
    expect($ortu->fresh()->pekerjaan)->toBe('Wiraswasta');
    expect($person->fresh()->nama_lengkap)->toBe('Nama Baru Ortu');
    expect($person->fresh()->alamat_jalan)->toBe('Alamat Baru');
    expect($user->fresh()->name)->toBe('Nama Baru Ortu');
});
