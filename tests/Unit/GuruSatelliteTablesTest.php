<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\RiwayatPendidikanGuru;
use App\Models\SertifikasiGuru;
use App\Models\User;
use App\Models\Yayasan;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('relates riwayat pendidikan and sertifikasi to a guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567890099',
        'nama' => 'Guru Uji',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_mapel',
        'status_kepegawaian' => 'GTY',
    ]);

    RiwayatPendidikanGuru::create([
        'guru_id' => $guru->id,
        'jenjang_pendidikan' => 'S1',
        'sekolah_formal' => 'Universitas Negeri',
        'tahun_masuk' => 2010,
        'tahun_lulus' => 2014,
    ]);

    SertifikasiGuru::create([
        'guru_id' => $guru->id,
        'jenis_sertifikasi' => 'Sertifikasi Guru',
        'nomor_sertifikat' => 'SERT-001',
        'tahun_sertifikasi' => 2018,
    ]);

    expect($guru->riwayatPendidikan)->toHaveCount(1);
    expect($guru->sertifikasi)->toHaveCount(1);
});
