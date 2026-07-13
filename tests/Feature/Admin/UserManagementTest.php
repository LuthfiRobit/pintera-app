<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsUserManager(): User
{
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('denies access to a user without users.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
});

it('lets a yayasan-scoped manager create a staff account with a role and a lembaga', function () {
    $manager = actingAsUserManager();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Kepala Sekolah Satu',
        'email' => 'kepsek1@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'role' => 'kepala_sekolah',
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'kepsek1@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->lembaga_id)->toBe($lembaga->id);
    expect($created->hasRole('kepala_sekolah'))->toBeTrue();
});

it('forces lembaga_id to the acting lembaga-scoped manager\'s own lembaga, ignoring submitted input', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole('admin_administrasi');

    Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Staf Keuangan',
        'email' => 'keuangan1@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $otherLembaga->id,
        'role' => 'admin_keuangan',
    ]);

    $created = User::withoutGlobalScopes()->where('email', 'keuangan1@example.test')->first();
    expect($created->lembaga_id)->toBe($ownLembaga->id);
});

it('requires a lembaga when creating a user with a lembaga-scoped role', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Tanpa Lembaga',
        'email' => 'tanpalembaga@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'kepala_sekolah',
    ])->assertSessionHasErrors('lembaga_id');

    expect(User::withoutGlobalScopes()->where('email', 'tanpalembaga@example.test')->exists())->toBeFalse();
});

it('deactivates a staff account so it can no longer log in', function () {
    $manager = actingAsUserManager();
    $staff = User::factory()->create(['is_active' => true]);

    $this->actingAs($manager)->patch(route('admin.users.toggle-active', $staff))
        ->assertRedirect(route('admin.users.index'));

    expect($staff->fresh()->is_active)->toBeFalse();
});

it('lets a user manager update an existing staff account\'s name and email', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $staff = User::factory()->create(['name' => 'Old Name', 'email' => 'oldemail@example.test']);
    $staff->assignRole('kepala_sekolah');

    $this->actingAs($manager)->put(route('admin.users.update', $staff), [
        'name' => 'New Name',
        'email' => 'newemail@example.test',
        'role' => 'kepala_sekolah',
    ])->assertRedirect(route('admin.users.index'));

    $updated = $staff->fresh();
    expect($updated->name)->toBe('New Name');
    expect($updated->email)->toBe('newemail@example.test');
});

it('denies access to admin.users.edit for a user without users.edit permission', function () {
    Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['users.view']);

    $viewer = User::factory()->create();
    $viewer->assignRole($role);

    $staff = User::factory()->create();

    $this->actingAs($viewer)->get(route('admin.users.edit', $staff))->assertForbidden();
});

it('refuses to let a lembaga-scoped manager assign a yayasan-scoped role to a new user', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($lembagaRole);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'yayasan_super_admin',
    ])->assertSessionHasErrors('role');

    expect(User::withoutGlobalScopes()->where('email', 'sneaky@example.test')->exists())->toBeFalse();
});
