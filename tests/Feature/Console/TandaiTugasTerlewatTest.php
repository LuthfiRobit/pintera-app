<?php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Yayasan;

it('marks tugas with no submissions past batas_selesai_pada as terlewat', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'kategori_masalah' => 'x', 'deskripsi' => 'x']);

    $tugasTanpaSubmission = KasusTugas::factory()->create([
        'kasus_id' => $kasus->id, 'status' => 'ditugaskan',
        'batas_selesai_pada' => now()->subDay()->toDateString(),
    ]);
    $tugasBelumJatuhTempo = KasusTugas::factory()->create([
        'kasus_id' => $kasus->id, 'status' => 'ditugaskan',
        'batas_selesai_pada' => now()->addDay()->toDateString(),
    ]);
    $tugasSudahAdaSubmission = KasusTugas::factory()->create([
        'kasus_id' => $kasus->id, 'status' => 'dikerjakan',
        'batas_selesai_pada' => now()->subDay()->toDateString(),
    ]);
    KasusTugasSubmission::factory()->create(['tugas_id' => $tugasSudahAdaSubmission->id]);

    $this->artisan('kasus:tandai-tugas-terlewat')->assertExitCode(0);

    expect($tugasTanpaSubmission->refresh()->status->value)->toBe('terlewat');
    expect($tugasBelumJatuhTempo->refresh()->status->value)->toBe('ditugaskan');
    expect($tugasSudahAdaSubmission->refresh()->status->value)->toBe('dikerjakan');
});
