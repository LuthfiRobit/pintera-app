<?php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates an akun pendaftar with a hashed password and datetime-cast email_verified_at', function () {
    $akun = AkunPendaftar::factory()->create([
        'email' => 'siswa@example.test',
        'password' => 'rahasia123',
    ]);

    expect($akun->email)->toBe('siswa@example.test');
    expect($akun->password)->not->toBe('rahasia123');
    expect(\Illuminate\Support\Facades\Hash::check('rahasia123', $akun->password))->toBeTrue();
    expect($akun->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('exposes a pendaftaran relation and a matching inverse on Pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'akun_pendaftar_id' => $akun->id,
    ]);

    expect($akun->pendaftaran)->toHaveCount(1);
    expect($akun->pendaftaran->first()->id)->toBe($pendaftaran->id);
    expect($pendaftaran->akunPendaftar->id)->toBe($akun->id);
});

it('resolves a working portal guard backed by the akun_pendaftar provider', function () {
    $akun = AkunPendaftar::factory()->create();

    auth('portal')->login($akun);

    expect(auth('portal')->check())->toBeTrue();
    expect(auth('portal')->id())->toBe($akun->id);
    expect(auth('web')->check())->toBeFalse();
});
