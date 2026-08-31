<?php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;

it('creates an orang_tua profile with a nullable-lembaga user', function () {
    $user = User::factory()->create(['lembaga_id' => null, 'username' => '3201234567891111']);

    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id,
        'nama_lengkap' => 'Budi Santoso',
        'nik' => '3201234567891111',
        'no_hp' => '081234567890',
    ]);

    expect($orangTua->user->id)->toBe($user->id);
    expect($orangTua->user->lembaga_id)->toBeNull();
});

it('links a siswa and an orang tua through the pivot with hubungan and is_kontak_utama', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();

    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $linked = $siswa->orangTua()->first();
    expect($linked->id)->toBe($orangTua->id);
    expect($linked->pivot->hubungan)->toBe('ayah');
    expect((bool) $linked->pivot->is_kontak_utama)->toBeTrue();

    $reverse = $orangTua->siswa()->first();
    expect($reverse->id)->toBe($siswa->id);
});

it('lets the same orang tua link to siswa in two different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $orangTua = OrangTua::factory()->create();

    $siswaA->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $siswaB->orangTua()->attach($orangTua->id, ['hubungan' => 'wali', 'is_kontak_utama' => true]);

    expect($orangTua->siswa()->count())->toBe(2);
});
