<?php

use App\Services\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('uses a human-readable Indonesian label for every module added in prior sub-tasks, not the ucfirst() fallback', function () {
    $modulesToCheck = [
        'rapor' => 'Rapor',
        'komponen-penilaian' => 'Komponen Penilaian',
        'kasus' => 'Kasus',
        'pengadaan' => 'Pengadaan',
        'sarpras' => 'Sarana & Prasarana',
        'rpp' => 'RPP',
    ];

    foreach ($modulesToCheck as $module => $expectedSubstring) {
        Permission::firstOrCreate(['name' => "{$module}.view", 'guard_name' => 'web']);
    }

    $grouped = PermissionCatalog::grouped();
    $labelsByModule = collect($grouped)->pluck('label', 'module');

    foreach ($modulesToCheck as $module => $expectedSubstring) {
        expect($labelsByModule[$module])->not->toBe(ucfirst($module));
    }
});
