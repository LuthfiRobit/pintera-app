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

it('gives distinct labels to permissions that share a noun but differ in verb under a 3-segment name', function () {
    // Permission seperti 'sarpras.aset.view' dan 'sarpras.aset.manage' dulu sama-sama
    // dilabeli "Aset" saja (hanya baca segmen kedua), sehingga tampak sebagai duplikat
    // di UI matrix Peran padahal keduanya permission yang berbeda.
    Permission::firstOrCreate(['name' => 'sarpras.aset.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sarpras.aset.manage', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'pengadaan.proposal.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'pengadaan.proposal.delete', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.ajukan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.approve', 'guard_name' => 'web']);

    $grouped = collect(PermissionCatalog::grouped())->keyBy('module');

    $sarprasLabels = collect($grouped['sarpras']['permissions'])->pluck('label', 'name');
    expect($sarprasLabels['sarpras.aset.view'])->not->toBe($sarprasLabels['sarpras.aset.manage']);

    $pengadaanLabels = collect($grouped['pengadaan']['permissions'])->pluck('label', 'name');
    expect($pengadaanLabels['pengadaan.proposal.create'])->not->toBe($pengadaanLabels['pengadaan.proposal.delete']);

    $kehadiranLabels = collect($grouped['kehadiran-sdm']['permissions'])->pluck('label', 'name');
    expect($kehadiranLabels['kehadiran-sdm.izin.ajukan'])->not->toBe($kehadiranLabels['kehadiran-sdm.izin.approve']);
});
