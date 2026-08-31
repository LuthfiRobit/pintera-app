<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'guru.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'guru.edit', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
});

it('creates a Guru with identity data routed through PersonService', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.create', 'guru.view', 'guru.edit']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->post(route('admin.guru.store'), [
        'nama' => 'Guru Baru',
        'email' => 'guru.baru@example.test',
        'nik' => '5555555555555555',
        'nip' => '199001012020121001',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '1990-01-01',
        'agama' => 'Islam',
        'no_hp' => '081200000000',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response->assertRedirect(route('admin.guru.index'));
    $guru = Guru::where('nip', '199001012020121001')->first();
    expect($guru)->not->toBeNull();
    expect($guru->person_id)->not->toBeNull();
    expect($guru->nama)->toBe('Guru Baru');
    expect($guru->nik)->toBe('5555555555555555');

    $person = Person::withoutGlobalScopes()->find($guru->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->user_id)->not->toBeNull();
});

it('updates a Guru identity through PersonService', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'guru.lama@example.test']);
    $person = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'user_id' => $user->id,
        'nama_lengkap' => 'Nama Lama',
        'nik' => '5555555555555556',
        'email' => 'guru.lama@example.test',
    ]);
    $guru = Guru::factory()->create([
        'lembaga_id' => $lembaga->id,
        'person_id' => $person->id,
        'user_id' => $user->id,
        'nip' => '199001012020121002',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);
    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.create', 'guru.view', 'guru.edit']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->put(route('admin.guru.update', $guru), [
        'nama' => 'Nama Baru',
        'email' => 'guru.baru@example.test',
        'nik' => '5555555555555556',
        'nip' => '199001012020121002',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response->assertRedirect(route('admin.guru.index'));
    expect($guru->fresh()->nama)->toBe('Nama Baru');
    expect($person->fresh()->nama_lengkap)->toBe('Nama Baru');
    expect($user->fresh()->name)->toBe('Nama Baru');
    expect($user->fresh()->email)->toBe('guru.baru@example.test');
});
