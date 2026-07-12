<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('denies access to a user without manage-lembaga permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.lembaga.index'))->assertForbidden();
});

it('lets a yayasan-scoped user create a new lembaga', function () {
    Permission::firstOrCreate(['name' => 'manage-lembaga', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo('manage-lembaga');
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();

    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301234',
        'nama' => 'SMA Pintera Tiga',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
    ])->assertRedirect(route('admin.lembaga.index'));

    expect(Lembaga::where('npsn', '20301234')->exists())->toBeTrue();
});

it('forbids a lembaga-scoped user from editing a lembaga that is not their own', function () {
    Permission::firstOrCreate(['name' => 'manage-lembaga', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-lembaga');

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.lembaga.edit', $otherLembaga))->assertForbidden();
});

it('lets a lembaga-scoped user edit their own lembaga', function () {
    Permission::firstOrCreate(['name' => 'manage-lembaga', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-lembaga');

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $ownLembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $ownLembaga->npsn,
        'nama' => 'Nama Baru',
        'bentuk_pendidikan' => $ownLembaga->bentuk_pendidikan,
        'status_sekolah' => $ownLembaga->status_sekolah,
        'naungan' => $ownLembaga->naungan,
    ])->assertRedirect(route('admin.lembaga.index'));

    expect($ownLembaga->fresh()->nama)->toBe('Nama Baru');
});
