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
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
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
        'roles' => ['kepala_sekolah'],
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
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
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
        'roles' => ['bendahara_lembaga'],
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
        'roles' => ['kepala_sekolah'],
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
        'roles' => ['kepala_sekolah'],
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

it('includes siswa accounts in the default (Semua) Pengguna list', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.included', 'email' => 'siswa.included@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.included', 'email' => 'staff.included@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('siswa.included@example.test');
    $response->assertSee('staff.included@example.test');
});

it('excludes siswa accounts when the lembaga scope chip is active', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.hidden', 'email' => 'siswa.hidden@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.shown', 'email' => 'staff.shown@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index', ['scope_group' => 'lembaga']));

    $response->assertOk();
    $response->assertDontSee('siswa.hidden@example.test');
    $response->assertSee('staff.shown@example.test');
});

it('shows only siswa accounts when the siswa scope chip is active', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.only', 'email' => 'siswa.only@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.excluded', 'email' => 'staff.excluded@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index', ['scope_group' => 'siswa']));

    $response->assertOk();
    $response->assertSee('siswa.only@example.test');
    $response->assertDontSee('staff.excluded@example.test');
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
        'roles' => ['siswa'],
    ])->assertNotFound();

    $this->actingAs($manager)->patch(route('admin.users.toggle-active', $siswaUser))->assertNotFound();

    $fresh = $siswaUser->fresh();
    expect($fresh->name)->not->toBe('Hacked Name');
    expect($fresh->hasRole('siswa'))->toBeTrue();
});

it('404s on edit, update, and toggle-active for an orang_tua-role user, since orang tua accounts are managed only from the Orang Tua module', function () {
    $manager = actingAsUserManager();

    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser = User::factory()->create(['username' => 'ortu.guarded', 'email' => null]);
    $orangTuaUser->assignRole('orang_tua');

    $this->actingAs($manager)->get(route('admin.users.edit', $orangTuaUser))->assertNotFound();

    $this->actingAs($manager)->put(route('admin.users.update', $orangTuaUser), [
        'name' => 'Hacked Name',
        'email' => 'hacked2@example.test',
        'roles' => ['orang_tua'],
    ])->assertNotFound();

    $this->actingAs($manager)->patch(route('admin.users.toggle-active', $orangTuaUser))->assertNotFound();

    $fresh = $orangTuaUser->fresh();
    expect($fresh->name)->not->toBe('Hacked Name');
    expect($fresh->hasRole('orang_tua'))->toBeTrue();
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
        'roles' => ['yayasan_super_admin'],
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
        'roles' => ['kepala_sekolah'],
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
        'roles' => ['yayasan_super_admin'],
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
        'roles' => ['yayasan_super_admin'],
    ])->assertSessionHasErrors('roles');

    expect(User::withoutGlobalScopes()->where('email', 'sneaky@example.test')->exists())->toBeFalse();
});

it('lets a platform_super_admin assign a yayasan-scoped role to a new user', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $platformRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $platformRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $platformAdmin = User::factory()->create();
    $platformAdmin->assignRole($platformRole);

    $this->actingAs($platformAdmin)->post(route('admin.users.store'), [
        'name' => 'Admin Yayasan Baru dari Platform',
        'email' => 'dariplatform@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['yayasan_super_admin'],
    ])->assertRedirect(route('admin.users.index'));

    expect(User::withoutGlobalScopes()->where('email', 'dariplatform@example.test')->exists())->toBeTrue();
});

it('does not strip the pegawai_lembaga baseline role when updating a guru account that only ever had "guru" assigned directly', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $guru = User::factory()->create(['name' => 'Guru Lama', 'email' => 'gurulama@example.test', 'lembaga_id' => $lembaga->id]);
    $guru->assignRole('guru');

    expect($guru->hasRole('pegawai_lembaga'))->toBeFalse();

    $this->actingAs($manager)->put(route('admin.users.update', $guru), [
        'name' => 'Guru Baru',
        'email' => 'gurulama@example.test',
        'roles' => ['guru'],
    ])->assertRedirect(route('admin.users.index'));

    $updated = $guru->fresh();
    expect($updated->hasRole('guru'))->toBeTrue();
    expect($updated->hasRole('pegawai_lembaga'))->toBeTrue();
});

it('assigns multiple functional roles at once plus a single shared pegawai_lembaga baseline', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Wakasek Merangkap Guru',
        'email' => 'wakasekguru@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'roles' => ['wakasek_kurikulum', 'guru'],
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'wakasekguru@example.test')->first();
    expect($created->hasRole('wakasek_kurikulum'))->toBeTrue();
    expect($created->hasRole('guru'))->toBeTrue();
    expect($created->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($created->roles()->count())->toBe(3);
});

it('does not add any carrier baseline role for a purely yayasan-scoped role selection', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Bendahara Yayasan Baru',
        'email' => 'bendaharayayasan@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['bendahara_yayasan'],
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'bendaharayayasan@example.test')->first();
    expect($created->hasRole('pegawai_lembaga'))->toBeFalse();
    expect($created->hasRole('pegawai_yayasan'))->toBeFalse();
});

it('rejects siswa, orang_tua, and carrier role names submitted directly via the roles array', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Percobaan Siswa',
        'email' => 'percobaansiswa@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['siswa'],
    ])->assertSessionHasErrors('roles.0');

    expect(User::withoutGlobalScopes()->where('email', 'percobaansiswa@example.test')->exists())->toBeFalse();
});

it('rejects a multi-role selection when any single role exceeds the acting manager\'s scope rank', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    Role::firstOrCreate(['name' => 'bendahara_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($lembagaRole);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Percobaan Campuran',
        'email' => 'percobaancampuran@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'roles' => ['bendahara_lembaga', 'yayasan_super_admin'],
    ])->assertSessionHasErrors('roles');

    expect(User::withoutGlobalScopes()->where('email', 'percobaancampuran@example.test')->exists())->toBeFalse();
});

it('does not show guru as a selectable role option on the create form', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'wali_kelas', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $response = $this->actingAs($manager)->get(route('admin.users.create'));

    $response->assertOk();
    $rolesByGroup = $response->viewData('rolesByGroup');
    $allRoleNames = $rolesByGroup->flatten()->pluck('name')->values()->all();

    expect($allRoleNames)->not->toContain('guru');
    expect($allRoleNames)->toContain('guru_bk');
    expect($allRoleNames)->toContain('wali_kelas');
});



