<?php

// tests/Unit/GuruJabatanTambahanSeederTest.php

use App\Models\GuruJabatanTambahan;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruJabatanTambahanSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\JabatanTambahanMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder)->run();
    (new RoleSeeder)->run();
    (new JabatanTambahanMasterSeeder)->run();
    Yayasan::factory()->create();
    (new LembagaSeeder)->run();
    (new UserSeeder)->run();
    (new GuruSeeder)->run();
});

it('assigns Wali Kelas to the guru who has one, and Wakil Kepala Sekolah Kurikulum to another', function () {
    (new GuruJabatanTambahanSeeder)->run();

    $sari = User::where('email', 'sari.wulandari@demo.test')->first();
    $guruSari = $sari->guru;
    expect(GuruJabatanTambahan::where('guru_id', $guruSari->id)->exists())->toBeTrue();

    $hendra = User::where('email', 'hendra.gunawan@demo.test')->first();
    $guruHendra = $hendra->guru;
    expect(GuruJabatanTambahan::where('guru_id', $guruHendra->id)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new GuruJabatanTambahanSeeder)->run();
    (new GuruJabatanTambahanSeeder)->run();

    expect(GuruJabatanTambahan::count())->toBe(4);
});
