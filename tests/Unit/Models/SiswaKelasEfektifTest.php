<?php

use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('mengembalikan kelas() untuk siswa aktif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => StatusSiswa::Aktif->value]);

    expect($siswa->kelas_efektif?->id)->toBe($kelas->id);
});

it('mengembalikan kelasTerakhir() untuk siswa non-aktif dengan kelas_terakhir_id terisi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => null,
        'kelas_terakhir_id' => $kelas->id,
        'status' => StatusSiswa::Keluar->value,
    ]);

    expect($siswa->kelas_efektif?->id)->toBe($kelas->id);
});

it('mengembalikan null kalau kelas_id maupun kelas_terakhir_id kosong', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => null, 'status' => StatusSiswa::Aktif->value]);

    expect($siswa->kelas_efektif)->toBeNull();
});
