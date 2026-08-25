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

    // A correctly-provisioned yayasan-scope account always has yayasan_id set (see
    // UserController::store()) - TenantScope now scopes an empty active_lembaga_id session
    // down to the actor's own yayasan (fail-closed otherwise), so tests needing the manager
    // to see a $staff row must create that row under a Lembaga belonging to this SAME yayasan.
    $user = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
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

    Role::firstOrCreate(['name' => 'bendahara_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Staf Keuangan',
        'email' => 'keuangan1@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $otherLembaga->id,
        'role' => 'bendahara_lembaga',
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
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $staff = User::factory()->create(['is_active' => true, 'lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->patch(route('admin.users.toggle-active', $staff))
        ->assertRedirect(route('admin.users.index'));

    expect($staff->fresh()->is_active)->toBeFalse();
});

it('lets a user manager update an existing staff account\'s name and email', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $staff = User::factory()->create(['name' => 'Old Name', 'email' => 'oldemail@example.test', 'lembaga_id' => $lembaga->id]);
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

    $yayasan = Yayasan::factory()->create();
    $viewer = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $viewer->assignRole($role);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $staff = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($viewer)->get(route('admin.users.edit', $staff))->assertForbidden();
});

it('excludes siswa accounts from the staff Pengguna list', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.excluded', 'email' => 'siswa.excluded@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.included', 'email' => 'staff.included@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertDontSee($siswaUser->username);
    $response->assertDontSee('siswa.excluded@example.test');
    $response->assertSee('staff.included@example.test');
});

it('404s on edit, update, and toggle-active for a siswa-role user, since siswa accounts are managed only from the Siswa module', function () {
    $manager = actingAsUserManager();

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaUser = User::factory()->create(['username' => 'siswa.guarded', 'email' => null]);
    $siswaUser->assignRole('siswa');

    $this->actingAs($manager)->get(route('admin.users.edit', $siswaUser))->assertNotFound();

    $this->actingAs($manager)->put(route('admin.users.update', $siswaUser), [
        'name' => 'Hacked Name',
        'email' => 'hacked@example.test',
        'role' => 'siswa',
    ])->assertNotFound();

    $this->actingAs($manager)->patch(route('admin.users.toggle-active', $siswaUser))->assertNotFound();

    $fresh = $siswaUser->fresh();
    expect($fresh->name)->not->toBe('Hacked Name');
    expect($fresh->hasRole('siswa'))->toBeTrue();
});

it('sets yayasan_id on a newly created yayasan-scoped staff account, inherited from the acting manager', function () {
    $yayasan = Yayasan::factory()->create();
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Admin Yayasan Baru',
        'email' => 'adminyayasanbaru@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'yayasan_super_admin',
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'adminyayasanbaru@example.test')->first();
    expect($created->yayasan_id)->toBe($yayasan->id);
});

it('leaves yayasan_id null when creating a lembaga-scoped staff account', function () {
    $manager = actingAsUserManager();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Kepala Sekolah Dua',
        'email' => 'kepsek2@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'role' => 'kepala_sekolah',
    ]);

    $created = User::withoutGlobalScopes()->where('email', 'kepsek2@example.test')->first();
    expect($created->yayasan_id)->toBeNull();
});

it('sets yayasan_id when updating a staff account to a yayasan-scoped role', function () {
    $yayasan = Yayasan::factory()->create();
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasanRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasanRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($yayasanRole);

    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $staff = User::factory()->create(['name' => 'Calon Admin Yayasan', 'email' => 'calonadminyayasan@example.test', 'lembaga_id' => $lembaga->id]);
    $staff->assignRole('kepala_sekolah');

    $this->actingAs($manager)->put(route('admin.users.update', $staff), [
        'name' => 'Calon Admin Yayasan',
        'email' => 'calonadminyayasan@example.test',
        'role' => 'yayasan_super_admin',
    ])->assertRedirect(route('admin.users.index'));

    expect($staff->fresh()->yayasan_id)->toBe($yayasan->id);
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
