<?php

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts sumber_data and status to their enums, and tanggal_lahir to a date', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'nis' => '2026001',
        'nisn' => '0012345678',
        'nama_lengkap' => 'Budi Santoso',
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '2015-03-10',
        'sumber_data' => SumberDataSiswa::Manual->value,
        'status' => StatusSiswa::Aktif->value,
    ]);

    $fresh = $siswa->fresh();
    expect($fresh->sumber_data)->toBe(SumberDataSiswa::Manual);
    expect($fresh->status)->toBe(StatusSiswa::Aktif);
    expect($fresh->tanggal_lahir)->toBeInstanceOf(Carbon::class);
});

it('can optionally belong to a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    expect($siswa->kelas->id)->toBe($kelas->id);
});

it('allows kelas_id, calon_murid_id, and pendaftaran_asal_id to all be null', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => null,
        'calon_murid_id' => null,
        'pendaftaran_asal_id' => null,
    ]);

    expect($siswa->fresh()->kelas_id)->toBeNull();
    expect($siswa->fresh()->calon_murid_id)->toBeNull();
    expect($siswa->fresh()->pendaftaran_asal_id)->toBeNull();
});
