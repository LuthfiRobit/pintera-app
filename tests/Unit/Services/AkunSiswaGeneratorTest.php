<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Yayasan;
use App\Services\AkunSiswaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a User with username = kode_lembaga-nis, password = nis, and the siswa role', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);

    $user = app(AkunSiswaGenerator::class)->buat('Ahmad Fauzan', '2026001', $lembaga);

    expect($user->username)->toBe('SMPPRM-2026001');
    expect($user->name)->toBe('Ahmad Fauzan');
    expect($user->email)->toBeNull();
    expect($user->lembaga_id)->toBe($lembaga->id);
    expect($user->is_active)->toBeTrue();
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('2026001', $user->password))->toBeTrue();
    expect($user->hasRole('siswa'))->toBeTrue();
});
