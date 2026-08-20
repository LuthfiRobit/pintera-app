<?php

use App\Services\PermissionUsageScanner;
use Tests\TestCase;

uses(TestCase::class);

it('does not use any permission in controllers, requests, policies, or blade views that is not registered in a seeder', function () {
    // Daftar permission yang SUDAH DIKETAHUI tidak konsisten SAAT test ini ditulis, dan SUDAH
    // disetujui user untuk dibiarkan sementara (bukan diperbaiki otomatis oleh test ini).
    // KOSONG per audit terakhir (lihat Task 3 plan RBAC v2) - satu-satunya temuan
    // (pola-jam.kelola) sudah diperbaiki sebelum test ini ditulis. JANGAN tambahkan entri baru
    // ke sini tanpa persetujuan eksplisit user - kalau test ini gagal karena permission BARU
    // yang tidak konsisten, laporkan dulu, jangan langsung di-allowlist.
    // Variabel lokal (bukan konstanta top-level) sengaja dipilih supaya tidak berisiko
    // "cannot redeclare" kalau ada test file lain yang kebetulan pakai nama sama - Pest
    // me-require SEMUA file test ke satu proses PHP yang sama saat full suite jalan.
    $allowlistTemuanLama = [];

    $scanner = new PermissionUsageScanner();

    $used = array_keys($scanner->scanCodeUsage([
        'app/Http/Controllers',
        'app/Http/Requests',
        'app/Policies',
        'resources/views',
    ]));

    $registered = array_keys($scanner->scanSeederRegistrations([
        'database/seeders/PermissionSeeder.php',
        'database/seeders/PengadaanPermissionSeeder.php',
        'database/seeders/SarprasPermissionSeeder.php',
    ]));

    $rusak = array_values(array_diff($used, $registered, $allowlistTemuanLama));

    expect($rusak)->toBe([], 'Permission dipakai di kode tapi TIDAK terdaftar di seeder manapun (selalu gagal akses untuk siapa pun): '.implode(', ', $rusak));
});
