<?php

use App\Domains\Akademik\Enums\StatusSesiPembelajaran;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts status to the enum and allows a null jadwal_pelajaran_id for ad-hoc sessions like PKL', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $sesi = SesiPembelajaran::create([
        'jadwal_pelajaran_id' => null,
        'kelas_id' => $kelas->id,
        'guru_id' => $guru->id,
        'tanggal' => '2026-08-19',
        'jam_mulai' => '08:00',
        'jam_selesai' => '15:00',
        'materi' => 'PKL hari ke-1',
        'status' => StatusSesiPembelajaran::Terlaksana->value,
    ]);

    expect($sesi->fresh()->status)->toBe(StatusSesiPembelajaran::Terlaksana);
    expect($sesi->fresh()->jadwal_pelajaran_id)->toBeNull();
    expect($sesi->fresh()->guru->id)->toBe($guru->id);
});
