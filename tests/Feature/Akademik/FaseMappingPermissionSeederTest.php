<?php

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('grants fase-mapping permissions to operator_akademik after full seeding sequence', function () {
    (new PermissionSeeder())->run();
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $role = Role::where('name', 'operator_akademik')->first();

    expect($role->hasPermissionTo('fase-mapping.view'))->toBeTrue();
    expect($role->hasPermissionTo('fase-mapping.create'))->toBeTrue();
    expect($role->hasPermissionTo('fase-mapping.edit'))->toBeTrue();
    expect($role->hasPermissionTo('fase-mapping.delete'))->toBeTrue();
});

it('grants all fase-mapping permissions to yayasan_super_admin via blanket Permission::all()', function () {
    (new PermissionSeeder())->run();
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $role = Role::where('name', 'yayasan_super_admin')->first();

    expect($role->hasPermissionTo('fase-mapping.view'))->toBeTrue();
    expect(Permission::where('name', 'fase-mapping.delete')->exists())->toBeTrue();
});
