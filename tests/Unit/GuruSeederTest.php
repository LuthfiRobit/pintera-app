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

it('seeds a Guru profile for every guru-role user, with correct lembaga_id and jenis_ptk', function () {
    (new GuruSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $user = User::where('email', 'budi.santoso@alhikmah.sch.id')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    expect($guru)->not->toBeNull();
    expect($guru->lembaga_id)->toBe($smp->id);
    expect($guru->nik)->toBe('3273011503850001');
    expect($guru->status_kepegawaian)->toBe('PNS');
});

it('is idempotent when run twice', function () {
    (new GuruSeeder())->run();
    (new GuruSeeder())->run();

    expect(Guru::count())->toBe(6);
});
