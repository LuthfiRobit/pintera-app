<?php
// tests/Feature/KasusSesiTugasSchemaTest.php

use App\Enums\StatusKasusSesi;
use App\Enums\StatusKasusTugas;
use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Karyawan;
use App\Models\Siswa;
use App\Models\User;

it('creates a kasus_sesi row with expected defaults', function () {
    $sesi = \App\Models\KasusSesi::factory()->create();

    expect($sesi->status)->toBe(StatusKasusSesi::Terjadwal);
    expect($sesi->kasus)->toBeInstanceOf(Kasus::class);
});

it('creates a kasus_tugas row with a related submission', function () {
    $tugas = KasusTugas::factory()->create();
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);

    expect($tugas->status)->toBe(StatusKasusTugas::Ditugaskan);
    expect($tugas->submissions)->toHaveCount(1);
    expect($submission->tugas->id)->toBe($tugas->id);
});

it('resolves User::siswa() and User::karyawan()', function () {
    $siswaUser = User::factory()->create();
    $siswa = Siswa::factory()->create(['user_id' => $siswaUser->id]);

    $karyawanUser = User::factory()->create();
    $karyawan = Karyawan::factory()->create(['user_id' => $karyawanUser->id]);

    expect($siswaUser->refresh()->siswa->id)->toBe($siswa->id);
    expect($karyawanUser->refresh()->karyawan->id)->toBe($karyawan->id);
});
