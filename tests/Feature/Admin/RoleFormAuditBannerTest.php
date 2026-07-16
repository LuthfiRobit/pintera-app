<?php
// tests/Feature/Admin/RoleFormAuditBannerTest.php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the audit banner markup on the create-role page, ready to be populated by Alpine after a sync', function () {
    $admin = User::factory()->create();
    $admin->assignRole('yayasan_super_admin');

    $response = $this->actingAs($admin)->get(route('admin.roles.create'));

    $response->assertOk();
    $response->assertSee('auditMissingFromDatabase', false);
    $response->assertSee('auditUnusedInCode', false);
    $response->assertSee('permission baru ditemukan di kode');
    $response->assertSee('tidak dipakai di kode manapun');
});
