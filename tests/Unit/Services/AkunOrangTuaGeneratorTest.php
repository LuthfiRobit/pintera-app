<?php

use App\Models\Role;
use App\Models\User;
use App\Services\AkunOrangTuaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a User with username=nik, password hashed from nik, and lembaga_id null', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $orangTua = (new AkunOrangTuaGenerator())->buat('Siti Aminah', '3201234567892222', '081298765432');

    $user = $orangTua->user;
    expect($user->username)->toBe('3201234567892222');
    expect($user->lembaga_id)->toBeNull();
    expect($user->email)->toBeNull();
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('3201234567892222', $user->password))->toBeTrue();
    expect($user->hasRole('orang_tua'))->toBeTrue();

    expect($orangTua->nama_lengkap)->toBe('Siti Aminah');
    expect($orangTua->nik)->toBe('3201234567892222');
    expect($orangTua->no_hp)->toBe('081298765432');
});

it('stores optional email, alamat, and pekerjaan when provided', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $orangTua = (new AkunOrangTuaGenerator())->buat(
        'Ahmad Fauzan', '3201234567893333', '081200001111',
        'ahmad@example.test', 'Jl. Merdeka No. 1', 'Wiraswasta',
    );

    expect($orangTua->email)->toBe('ahmad@example.test');
    expect($orangTua->alamat)->toBe('Jl. Merdeka No. 1');
    expect($orangTua->pekerjaan)->toBe('Wiraswasta');
});
