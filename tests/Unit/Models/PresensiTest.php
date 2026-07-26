<?php

use App\Enums\StatusPresensi;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to a sesi pembelajaran and a siswa, casting status to the enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $sesi = SesiPembelajaran::create([
        'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'tanggal' => '2026-08-19',
        'jam_mulai' => '07:00', 'jam_selesai' => '07:35', 'status' => 'terlaksana',
    ]);

    $presensi = Presensi::create([
        'sesi_pembelajaran_id' => $sesi->id,
        'siswa_id' => $siswa->id,
        'status' => StatusPresensi::Hadir->value,
    ]);

    expect($presensi->fresh()->sesiPembelajaran->id)->toBe($sesi->id);
    expect($presensi->fresh()->siswa->id)->toBe($siswa->id);
    expect($presensi->fresh()->status)->toBe(StatusPresensi::Hadir);
});
