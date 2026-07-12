<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns yayasan as the widest scope when user has a yayasan-scoped role', function () {
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    expect($user->widestScopeLevel())->toBe('yayasan');
});

it('returns lembaga as the widest scope when user only has lembaga-scoped roles', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    expect($user->widestScopeLevel())->toBe('lembaga');
});

it('returns diri_sendiri when user has no roles', function () {
    $user = User::factory()->create();

    expect($user->widestScopeLevel())->toBe('diri_sendiri');
});

it('takes the widest scope when a user has multiple roles with different scopes', function () {
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $user = User::factory()->create();
    $user->assignRole(['yayasan_super_admin', 'kepala_sekolah']);

    expect($user->widestScopeLevel())->toBe('yayasan');
});
