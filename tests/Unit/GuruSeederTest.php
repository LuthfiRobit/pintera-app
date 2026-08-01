<?php
// tests/Unit/GuruSeederTest.php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new UserSeeder())->run();
});

it('seeds Guru profiles across all K-9 institutions', function () {
    (new GuruSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $gurus = Guru::where('lembaga_id', $lembaga->id)->get();
        expect($gurus->count())->toBeGreaterThanOrEqual(3);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $user = User::where('email', 'budi.santoso@permata.sch.id')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    expect($guru)->not->toBeNull();
    expect($guru->lembaga_id)->toBe($smp->id);
    expect($guru->nik)->toBe('3273011503850001');
    expect($guru->status_kepegawaian)->toBe('PNS');
});

it('is idempotent when run twice', function () {
    (new GuruSeeder())->run();
    (new GuruSeeder())->run();

    expect(Guru::count())->toBe(12);
});
