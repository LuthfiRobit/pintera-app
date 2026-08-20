<?php
// tests/Feature/KasusSchemaTest.php

use App\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;

it('creates a kasus linked to siswa and lembaga with default status diajukan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id,
        'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku',
        'deskripsi' => 'Contoh deskripsi kasus.',
    ]);

    expect($kasus->status)->toBe(StatusKasus::Diajukan);
    expect($kasus->siswa->id)->toBe($siswa->id);
    expect(StatusKasus::Diajukan->label())->toBe('Diajukan');
    expect(StatusKasus::MenungguConsent->label())->toBe('Menunggu Consent');
    expect(StatusKasus::Ditugaskan->label())->toBe('Ditugaskan');
});

it('creates exactly one kasus_consent row per jenis per kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan']);

    expect($kasus->consents()->count())->toBe(1);
    expect(fn () => KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('exposes User::orangTua() as the inverse of OrangTua::user()', function () {
    $orangTua = OrangTua::factory()->create();

    expect($orangTua->user->orangTua->id)->toBe($orangTua->id);
});
