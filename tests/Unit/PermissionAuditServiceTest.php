<?php
// tests/Unit/PermissionAuditServiceTest.php

use App\Services\PermissionAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatDirektoriUjiPermissionAudit(string $isiFile): string
{
    $dir = sys_get_temp_dir().'/permission-audit-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/FakeController.php', $isiFile);

    return $dir;
}

function hapusDirektoriUjiPermissionAudit(string $dir): void
{
    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
}

it('ignores a Laravel Policy-style two-argument authorize() call, since its bare ability name is not a Spatie permission string', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\nuse App\\Models\\Role;\nclass FakeController {\n    public function store() {\n        \$this->authorize('create', Role::class);\n    }\n    public function update(Role \$role) {\n        \$this->authorize('update', \$role);\n    }\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->toBe([]);
    expect(Permission::where('name', 'create')->exists())->toBeFalse();
    expect(Permission::where('name', 'update')->exists())->toBeFalse();

    hapusDirektoriUjiPermissionAudit($dir);
});

it('detects a permission used in code but missing from the database, and creates it', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->authorize('contoh-modul.aksi-baru');\n    }\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->toBe(['contoh-modul.aksi-baru']);
    expect(Permission::where('name', 'contoh-modul.aksi-baru')->where('guard_name', 'web')->exists())->toBeTrue();

    hapusDirektoriUjiPermissionAudit($dir);
});

it('is idempotent -- running audit twice does not duplicate or error', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->authorize('contoh-modul.aksi-dua-kali');\n    }\n}\n");

    (new PermissionAuditService([$dir]))->audit();
    $keduaKali = (new PermissionAuditService([$dir]))->audit();

    expect($keduaKali['missingFromDatabase'])->toBe([]);
    expect(Permission::where('name', 'contoh-modul.aksi-dua-kali')->count())->toBe(1);

    hapusDirektoriUjiPermissionAudit($dir);
});

it('reports a permission that exists in the database but is never referenced in code, without deleting it', function () {
    Permission::create(['name' => 'contoh-modul.tidak-terpakai', 'guard_name' => 'web']);
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {}\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['unusedInCode'])->toContain('contoh-modul.tidak-terpakai');
    expect(Permission::where('name', 'contoh-modul.tidak-terpakai')->exists())->toBeTrue();

    hapusDirektoriUjiPermissionAudit($dir);
});

it('does not list a permission that exists in both code and database', function () {
    Permission::create(['name' => 'contoh-modul.sudah-lengkap', 'guard_name' => 'web']);
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->authorize('contoh-modul.sudah-lengkap');\n    }\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->not->toContain('contoh-modul.sudah-lengkap');
    expect($result['unusedInCode'])->not->toContain('contoh-modul.sudah-lengkap');

    hapusDirektoriUjiPermissionAudit($dir);
});

it('detects ->can(...) the same way as authorize(...), and ignores dynamic arguments', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\n\$variabel = 'sesuatu';\necho auth()->user()->can('contoh-modul.dari-can');\necho auth()->user()->can(\$variabel);\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->toBe(['contoh-modul.dari-can']);

    hapusDirektoriUjiPermissionAudit($dir);
});

it('does not false-positive on an unrelated method name that merely contains "authorize" or "can"', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->reauthorize('tidak-relevan.jangan-tertangkap');\n        \$this->scan('juga-tidak-relevan.jangan-tertangkap');\n    }\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->toBe([]);

    hapusDirektoriUjiPermissionAudit($dir);
});
