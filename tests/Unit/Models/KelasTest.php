<?php

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to a lembaga and a tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
    ]);

    expect($kelas->fresh()->lembaga->id)->toBe($lembaga->id);
    expect($kelas->fresh()->tahunAjaran->id)->toBe($tahunAjaran->id);
});

it('optionally belongs to a wali kelas guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'wali_kelas_guru_id' => $guru->id,
    ]);

    expect($kelas->fresh()->waliKelas->id)->toBe($guru->id);
});

it('allows a null tingkat for PAUD-style kelompok naming', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelompok A',
        'tingkat' => null,
    ]);

    expect($kelas->fresh()->tingkat)->toBeNull();
});
