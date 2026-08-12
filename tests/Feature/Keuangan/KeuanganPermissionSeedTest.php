<?php
// tests/Feature/Keuangan/KeuanganPermissionSeedTest.php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('grants keuangan.akses to the orang_tua role after seeding', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');

    expect($user->can('keuangan.akses'))->toBeTrue();
});

it('does not grant keuangan.akses to the guru role', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('guru');

    expect($user->can('keuangan.akses'))->toBeFalse();
});
