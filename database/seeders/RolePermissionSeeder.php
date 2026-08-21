<?php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fixture RBAC ringan khusus untuk test (dipanggil via $this->seed(RolePermissionSeeder::class)
 * di puluhan file test). Sengaja TIDAK dipanggil dari DatabaseSeeder::run() — hanya membuat
 * permission + role tanpa data domain lain, supaya test RBAC-only bisa jalan cepat.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        (new PermissionSeeder())->run();
        (new RoleSeeder())->run();
    }
}
