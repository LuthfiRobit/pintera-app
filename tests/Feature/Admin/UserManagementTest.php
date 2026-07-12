<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsUserManager(): User
{
    Permission::firstOrCreate(['name' => 'manage-users', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo('manage-users');

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('denies access to a user without manage-users permission', function () {
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
    Permission::firstOrCreate(['name' => 'manage-users', 'guard_name' => 'web']);
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo('manage-users');

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
