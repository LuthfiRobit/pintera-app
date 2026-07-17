<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('denies access to a user without lembaga.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.lembaga.index'))->assertForbidden();
});

it('lets a yayasan-scoped user create a new lembaga', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
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
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.lembaga.edit', $otherLembaga))->assertForbidden();
});

it('filters the index by name or npsn when cari is given', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera Satu', 'npsn' => '20111111']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Pintera Dua', 'npsn' => '20222222']);

    $byName = $this->actingAs($manager)->get(route('admin.lembaga.index', ['cari' => 'Pintera Satu']));
    expect($byName->viewData('lembaga')->pluck('nama')->all())->toBe(['SD Pintera Satu']);

    $byNpsn = $this->actingAs($manager)->get(route('admin.lembaga.index', ['cari' => '20222222']));
    expect($byNpsn->viewData('lembaga')->pluck('nama')->all())->toBe(['SMP Pintera Dua']);
});

it('filters the index by bentuk pendidikan', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera', 'bentuk_pendidikan' => 'SD']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Pintera', 'bentuk_pendidikan' => 'SMP']);

    $response = $this->actingAs($manager)->get(route('admin.lembaga.index', ['bentuk' => 'SD']));

    expect($response->viewData('lembaga')->pluck('nama')->all())->toBe(['SD Pintera']);
});

it('filters the index by status sekolah', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga Negeri', 'status_sekolah' => 'negeri']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga Swasta', 'status_sekolah' => 'swasta']);

    $response = $this->actingAs($manager)->get(route('admin.lembaga.index', ['status' => 'negeri']));

    expect($response->viewData('lembaga')->pluck('nama')->all())->toBe(['Lembaga Negeri']);
});

it('paginates the index at 10 per page', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->count(12)->create(['yayasan_id' => $yayasan->id]);

    $response = $this->actingAs($manager)->get(route('admin.lembaga.index'));

    $response->assertOk();
    expect($response->viewData('lembaga'))->toHaveCount(10);
    expect($response->viewData('lembaga')->total())->toBe(12);
});

it('lets a lembaga-scoped user edit their own lembaga', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);

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
