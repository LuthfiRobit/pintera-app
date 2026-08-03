<?php
// tests/Feature/Admin/OrangTuaCrudTest.php

use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function actingAsOrangTuaManager(): User
{
    foreach (['orang-tua.view', 'orang-tua.create', 'orang-tua.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['orang-tua.view', 'orang-tua.create', 'orang-tua.edit']);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $manager = User::factory()->create();
    $manager->assignRole($role);

    return $manager;
}

function orangTuaFormPayload(array $overrides = []): array
{
    return array_merge([
        'nik' => '3201234567894444',
        'nama_lengkap' => 'Wali Murid Baru',
        'no_hp' => '081234500001',
        'email' => 'wali.baru@example.test',
        'alamat' => 'Jl. Contoh No. 5',
        'pekerjaan' => 'Karyawan Swasta',
    ], $overrides);
}

it('denies access to a user without orang-tua.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.orang-tua.index'))->assertForbidden();
});

it('creates both a User account and an OrangTua profile, with NIK as username and hashed password', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())
        ->assertRedirect(route('admin.orang-tua.index'));

    $orangTua = OrangTua::where('nama_lengkap', 'Wali Murid Baru')->first();
    expect($orangTua)->not->toBeNull();
    expect($orangTua->nik)->toBe('3201234567894444');

    $user = $orangTua->user;
    expect($user->username)->toBe('3201234567894444');
    expect($user->lembaga_id)->toBeNull();
    expect(Hash::check('3201234567894444', $user->password))->toBeTrue();
    expect($user->hasRole('orang_tua'))->toBeTrue();
});

it('rejects creating an orang tua with a NIK that is not exactly 16 digits', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload(['nik' => '12345']))
        ->assertSessionHasErrors('nik');

    expect(OrangTua::where('nama_lengkap', 'Wali Murid Baru')->exists())->toBeFalse();
});

it('rejects creating an orang tua with an empty no_hp', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload(['no_hp' => '']))
        ->assertSessionHasErrors('no_hp');
});

it('does not create a duplicate User when the NIK is already registered, and redirects to the existing profile', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())->assertRedirect();
    $existing = OrangTua::where('nik', '3201234567894444')->firstOrFail();

    $response = $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload([
        'nama_lengkap' => 'Nama Berbeda',
    ]));

    $response->assertRedirect(route('admin.orang-tua.edit', $existing));
    expect(OrangTua::where('nik', '3201234567894444')->count())->toBe(1);
    expect(User::where('username', '3201234567894444')->count())->toBe(1);
});
