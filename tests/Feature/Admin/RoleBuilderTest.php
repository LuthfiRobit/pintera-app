<?php

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function actingAsSuperAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage-roles', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo('manage-roles');

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('denies access to a user without manage-roles permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
});

it('lets an authorized user create a role with a scope level and permissions', function () {
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'admin_perpustakaan',
        'scope_level' => 'lembaga',
        'permissions' => [Permission::where('name', 'manage-guru')->first()->id],
    ])->assertRedirect(route('admin.roles.index'));

    $created = Role::where('name', 'admin_perpustakaan')->first();
    expect($created->scope_level)->toBe('lembaga');
    expect($created->hasPermissionTo('manage-guru'))->toBeTrue();
});

it('lets an authorized user edit a non-protected role, including its scope level', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'editable', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'editable-renamed',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->name)->toBe('editable-renamed');
    expect($role->fresh()->scope_level)->toBe('yayasan');
});

it('does not let anyone change the scope_level of the protected super admin role', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $this->actingAs($admin)->put(route('admin.roles.update', $protected), [
        'name' => 'yayasan_super_admin',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    expect($protected->fresh()->scope_level)->toBe('yayasan');
});

it('refuses to delete a protected role', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $protected))->assertForbidden();

    expect(Role::find($protected->id))->not->toBeNull();
});

it('refuses to delete a role that still has assigned users', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'in-use', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    User::factory()->create()->assignRole($role);

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))
        ->assertRedirect()
        ->assertSessionHasErrors('role');

    expect(Role::find($role->id))->not->toBeNull();
});

it('refuses to let a lembaga-scoped role-manager create a yayasan-scoped role', function () {
    Permission::firstOrCreate(['name' => 'manage-roles', 'guard_name' => 'web']);
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo('manage-roles');
    $manager = User::factory()->create();
    $manager->assignRole($lembagaRole);

    $this->actingAs($manager)->post(route('admin.roles.store'), [
        'name' => 'sneaky_admin',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ])->assertSessionHasErrors('scope_level');

    expect(Role::where('name', 'sneaky_admin')->exists())->toBeFalse();
});
