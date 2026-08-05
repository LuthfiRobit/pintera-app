<?php
// tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;

it('stores and casts a nullable tanggal on kasus_tugas_submission', function () {
    $kasus = Kasus::factory()->create();
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'frekuensi' => 'harian']);

    $submission = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Bukti hari ini.',
        'tanggal' => '2026-08-10',
    ]);

    expect($submission->fresh()->tanggal)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($submission->fresh()->tanggal->toDateString())->toBe('2026-08-10');

    $tanpaTanggal = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Bukti tanpa tanggal (frekuensi lain).',
    ]);

    expect($tanpaTanggal->fresh()->tanggal)->toBeNull();
});
