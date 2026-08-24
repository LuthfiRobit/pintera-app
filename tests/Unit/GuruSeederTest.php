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

it('seeds Guru profiles for the SD institution', function () {
    (new GuruSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $gurus = Guru::where('lembaga_id', $sdit->id)->get();
    expect($gurus->count())->toBe(15);

    $user = User::where('email', 'hendra.gunawan@demo.test')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    expect($guru)->not->toBeNull();
    expect($guru->lembaga_id)->toBe($sdit->id);
    expect($guru->nik)->toBe('3273010108820004');
    expect($guru->status_kepegawaian)->toBe('PNS');
});

it('is idempotent when run twice', function () {
    (new GuruSeeder())->run();
    (new GuruSeeder())->run();

    expect(Guru::count())->toBe(15);
});
