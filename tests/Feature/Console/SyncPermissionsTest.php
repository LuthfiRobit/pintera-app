<?php

use App\Http\Controllers\Admin\RaporController;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

it('creates a permission referenced via a 3-segment authorize() call, which the old broken regex could never catch', function () {
    // Bukti regresi: sebelum diperbaiki, tool ini TIDAK PERNAH bisa mendeteksi permission
    // bersegmen-3 seperti yang dipakai di seluruh modul Pengadaan (mis. pengadaan.lpj.submit) -
    // dibuktikan lewat eksekusi regex lama secara langsung sebelum plan ini ditulis (0 match).
    expect(Permission::where('name', 'pengadaan.lpj.submit')->exists())->toBeFalse();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'pengadaan.lpj.submit')->exists())->toBeTrue();
});

it('creates a permission referenced only via canAny([...]), which the old tool never scanned for', function () {
    expect(Permission::where('name', 'rapor.approve')->exists())->toBeFalse();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'rapor.approve')->exists())->toBeTrue();
});

it('creates a permission referenced only inside a FormRequest authorize() method', function () {
    expect(Permission::where('name', 'rapor.ajukan')->exists())->toBeFalse();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'rapor.ajukan')->exists())->toBeTrue();
});

it('reports stale permissions that exist in the database but are referenced by no code', function () {
    Permission::firstOrCreate(['name' => 'benar-benar.tidak-dipakai', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('benar-benar.tidak-dipakai')
        ->assertExitCode(0);
});

it('is idempotent - running it twice creates no duplicate rows', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);
    $firstCount = Permission::count();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::count())->toBe($firstCount);
});
