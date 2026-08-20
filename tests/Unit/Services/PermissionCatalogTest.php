<?php

use App\Services\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('uses the exact human-readable Indonesian label configured for each module added in prior sub-tasks', function () {
    // Perbandingan langsung ke label yang diharapkan, BUKAN "!== ucfirst($module)" - sebagian
    // modul (mis. 'rapor') sengaja punya label satu kata yang KEBETULAN identik dengan
    // ucfirst() dari nama modulnya sendiri ('Rapor' == ucfirst('rapor')), jadi perbandingan
    // ketidaksamaan itu akan salah menganggapnya "masih fallback" padahal itu memang label
    // yang benar dan sengaja dipilih.
    $expectedLabels = [
        'rapor' => 'Rapor',
        'komponen-penilaian' => 'Komponen Penilaian (TP)',
        'kasus' => 'Manajemen Kasus Siswa',
        'pengadaan' => 'Pengadaan Sarpras',
        'sarpras' => 'Sarana & Prasarana',
        'rpp' => 'Perangkat Ajar (RPP)',
    ];

    foreach ($expectedLabels as $module => $label) {
        Permission::firstOrCreate(['name' => "{$module}.view", 'guard_name' => 'web']);
    }

    $grouped = PermissionCatalog::grouped();
    $labelsByModule = collect($grouped)->pluck('label', 'module');

    foreach ($expectedLabels as $module => $label) {
        expect($labelsByModule[$module])->toBe($label);
    }
});
