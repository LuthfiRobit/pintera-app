<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('includes audit results alongside the module catalog in the permissions-catalog response', function () {
    $admin = User::factory()->create();
    $admin->assignRole('yayasan_super_admin');

    $response = $this->actingAs($admin)->getJson(route('admin.roles.permissions-catalog'));

    $response->assertOk();
    $response->assertJsonStructure(['modules', 'audit' => ['missingFromDatabase', 'unusedInCode']]);
});

it('still denies the permissions-catalog endpoint to a user without roles.create or roles.edit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('admin.roles.permissions-catalog'))->assertForbidden();
});
