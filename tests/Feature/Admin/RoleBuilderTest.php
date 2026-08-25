<?php

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function actingAsSuperAdmin(): User
{
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete']);

    $user = User::factory()->create(['yayasan_id' => \App\Models\Yayasan::factory()->create()->id]);
    $user->assignRole($role);

    return $user;
}

it('denies access to a user without roles.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
});

it('lets an authorized user create a role with a scope level and permissions', function () {
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'admin_perpustakaan',
        'scope_level' => 'lembaga',
        'permissions' => [Permission::where('name', 'guru.view')->first()->id],
    ])->assertRedirect(route('admin.roles.index'));

    $created = Role::where('name', 'admin_perpustakaan')->first();
    expect($created->scope_level)->toBe('lembaga');
    expect($created->hasPermissionTo('guru.view'))->toBeTrue();
});

it('syncs permissions when their ids arrive as strings, matching a real HTML checkbox submission', function () {
    // Regression test: a real browser form-urlencoded POST always sends checkbox
    // values as strings (e.g. "6"), never as native PHP ints. Pest's ->post() with
    // a PHP array literal does NOT reproduce this — it preserves the int type from
    // Permission::first()->id, which masked the original bug. Casting to string
    // here forces the same string-typed input a real submission produces.
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);
    $permissionId = Permission::where('name', 'guru.view')->first()->id;

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'admin_string_ids',
        'scope_level' => 'lembaga',
        'permissions' => [(string) $permissionId],
    ])->assertRedirect(route('admin.roles.index'));

    $created = Role::where('name', 'admin_string_ids')->first();
    expect($created->hasPermissionTo('guru.view'))->toBeTrue();
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
    User::factory()->create(['yayasan_id' => $admin->yayasan_id])->assignRole($role);

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))
        ->assertRedirect()
        ->assertSessionHasErrors('role');

    expect(Role::find($role->id))->not->toBeNull();
});

it('refuses to let a lembaga-scoped role-manager create a yayasan-scoped role', function () {
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete']);
    $manager = User::factory()->create();
    $manager->assignRole($lembagaRole);

    $this->actingAs($manager)->post(route('admin.roles.store'), [
        'name' => 'sneaky_admin',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ])->assertSessionHasErrors('scope_level');

    expect(Role::where('name', 'sneaky_admin')->exists())->toBeFalse();
});

it('returns a paginated, searchable HTML fragment sorted alphabetically by name', function () {
    $admin = actingAsSuperAdmin();
    Role::create(['name' => 'zzz_role', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    Role::create(['name' => 'aaa_role', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.roles.index'), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();
    $aaaPos = strpos($response->getContent(), 'aaa_role');
    $zzzPos = strpos($response->getContent(), 'zzz_role');
    expect($aaaPos)->not->toBeFalse();
    expect($aaaPos)->toBeLessThan($zzzPos);
});

it('filters the index fragment by search and scope', function () {
    $admin = actingAsSuperAdmin();
    Role::create(['name' => 'admin_perpustakaan', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    Role::create(['name' => 'admin_gudang', 'guard_name' => 'web', 'scope_level' => 'diri_sendiri']);

    $response = $this->actingAs($admin)->get(route('admin.roles.index', ['search' => 'perpustakaan']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $response->assertOk();
    $response->assertSee('admin_perpustakaan');
    $response->assertDontSee('admin_gudang');

    $response = $this->actingAs($admin)->get(route('admin.roles.index', ['scope' => 'diri_sendiri']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $response->assertOk();
    $response->assertSee('admin_gudang');
    $response->assertDontSee('admin_perpustakaan');
});

it('denies the index fragment to a user without roles.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.roles.index'), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertForbidden();
});

it('returns the permission catalog grouped by module', function () {
    $admin = actingAsSuperAdmin();
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $response = $this->actingAs($admin)->getJson(route('admin.roles.permissions-catalog'));

    $response->assertOk();
    $modules = collect($response->json('modules'))->keyBy('module');
    expect($modules->has('guru'))->toBeTrue();
    expect(collect($modules['guru']['permissions'])->pluck('name')->sort()->values()->all())
        ->toBe(['guru.create', 'guru.edit', 'guru.view']);
});

it('creates a role via AJAX and returns JSON instead of redirecting', function () {
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);

    $response = $this->actingAs($admin)->postJson(route('admin.roles.store'), [
        'name' => 'admin_ajax',
        'scope_level' => 'lembaga',
        'permissions' => [Permission::where('name', 'guru.view')->first()->id],
    ]);

    $response->assertCreated();
    expect(Role::where('name', 'admin_ajax')->exists())->toBeTrue();
});

it('returns a JSON 422 with field errors when an AJAX store fails validation via the scope ceiling check', function () {
    Permission::firstOrCreate(['name' => 'roles.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'roles.create', 'guard_name' => 'web']);
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['roles.view', 'roles.create']);
    $manager = User::factory()->create();
    $manager->assignRole($lembagaRole);

    $response = $this->actingAs($manager)->postJson(route('admin.roles.store'), [
        'name' => 'sneaky_ajax',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.scope_level'))->not->toBeNull();
    expect(Role::where('name', 'sneaky_ajax')->exists())->toBeFalse();
});

it('updates a role via AJAX and returns JSON instead of redirecting', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'ajax-editable', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->putJson(route('admin.roles.update', $role), [
        'name' => 'ajax-editable-renamed',
        'scope_level' => 'lembaga',
        'permissions' => [],
    ]);

    $response->assertOk();
    expect($role->fresh()->name)->toBe('ajax-editable-renamed');
});

it('deletes a role via AJAX and returns JSON instead of redirecting', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'ajax-deletable', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->deleteJson(route('admin.roles.destroy', $role));

    $response->assertOk();
    expect(Role::find($role->id))->toBeNull();
});

it('returns a JSON 422 instead of a redirect when an AJAX delete targets a role still in use', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'ajax-in-use', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    User::factory()->create(['yayasan_id' => $admin->yayasan_id])->assignRole($role);

    $response = $this->actingAs($admin)->deleteJson(route('admin.roles.destroy', $role));

    $response->assertStatus(422);
    expect($response->json('errors.role'))->not->toBeNull();
    expect(Role::find($role->id))->not->toBeNull();
});

it('renders the roles index page with the AJAX datatable-filter mount point instead of a server-rendered table', function () {
    $admin = actingAsSuperAdmin();

    $response = $this->actingAs($admin)->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertSee('dataTableFilter(', false);
});

it('renders the create-role page with the permission-matrix mount point', function () {
    $admin = actingAsSuperAdmin();

    $response = $this->actingAs($admin)->get(route('admin.roles.create'));

    $response->assertOk();
    $response->assertSee('roleForm(', false);
});

it('renders the edit-role page with the permission-matrix mount point pre-filled with the role\'s permissions', function () {
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'editable-matrix', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    $role->givePermissionTo('guru.view');

    $response = $this->actingAs($admin)->get(route('admin.roles.edit', $role));

    $response->assertOk();
    $response->assertSee('roleForm(', false);
    $response->assertSee((string) Permission::where('name', 'guru.view')->first()->id, false);
});

it('ignores a submitted name change for a protected role instead of throwing a 500', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $this->actingAs($admin)->put(route('admin.roles.update', $protected), [
        'name' => 'Nama Baru Yang Dicoba',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    expect($protected->fresh()->name)->toBe('yayasan_super_admin');
});

it('ignores a submitted name change for a protected role via AJAX, returning 200 not 500', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $response = $this->actingAs($admin)->putJson(route('admin.roles.update', $protected), [
        'name' => 'Nama Baru AJAX',
        'permissions' => [],
    ]);

    $response->assertOk();
    expect($protected->fresh()->name)->toBe('yayasan_super_admin');
});

it('lets a platform_super_admin create a role with scope_level platform', function () {
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $platformRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $platformRole->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete']);

    $admin = User::factory()->create();
    $admin->assignRole($platformRole);

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'admin_platform_baru',
        'scope_level' => 'platform',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    $created = Role::where('name', 'admin_platform_baru')->first();
    expect($created->scope_level)->toBe('platform');
});

it('refuses to let a yayasan-scoped role-manager create a platform-scoped role', function () {
    $admin = actingAsSuperAdmin();

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'sneaky_platform',
        'scope_level' => 'platform',
        'permissions' => [],
    ])->assertSessionHasErrors('scope_level');

    expect(Role::where('name', 'sneaky_platform')->exists())->toBeFalse();
});
