<?php
// tests/Feature/Admin/JenisTagihanMonitoringTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies access to monitoring page without jenis-tagihan.view permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)
        ->get(route('admin.jenis-tagihan.monitoring.index', $jenisTagihan))
        ->assertForbidden();
});

it('enforces tenant scope on monitoring page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga1 = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembaga2 = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    
    $user1 = User::factory()->create(['lembaga_id' => $lembaga1->id]);
    $user1->assignRole('admin_keuangan');

    $jenisTagihanLembaga2 = JenisTagihan::factory()->create(['lembaga_id' => $lembaga2->id]);

    $this->actingAs($user1)
        ->get(route('admin.jenis-tagihan.monitoring.index', $jenisTagihanLembaga2))
        ->assertNotFound(); // Implicit route model binding fails due to TenantScope
});

it('allows access to monitoring page with proper permission and tenant', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)
        ->get(route('admin.jenis-tagihan.monitoring.index', $jenisTagihan))
        ->assertOk();
});
