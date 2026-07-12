<?php

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts is_protected to boolean and stores scope_level', function () {
    $role = Role::create([
        'name' => 'test-role',
        'guard_name' => 'web',
        'scope_level' => 'lembaga',
    ]);

    $role->refresh();

    expect($role->scope_level)->toBe('lembaga');
    expect($role->is_protected)->toBeFalse();
    expect($role->is_protected)->toBeBool();
});

it('prevents deleting a protected role', function () {
    $role = Role::create([
        'name' => 'protected-role',
        'guard_name' => 'web',
        'scope_level' => 'yayasan',
        'is_protected' => true,
    ]);

    expect(fn () => $role->delete())->toThrow(RuntimeException::class);
});

it('prevents changing scope_level on a protected role', function () {
    $role = Role::create([
        'name' => 'protected-role-2',
        'guard_name' => 'web',
        'scope_level' => 'yayasan',
        'is_protected' => true,
    ]);

    $role->scope_level = 'lembaga';

    expect(fn () => $role->save())->toThrow(RuntimeException::class);
});
