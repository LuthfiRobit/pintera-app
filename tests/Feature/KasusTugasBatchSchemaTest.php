<?php
// tests/Feature/KasusTugasBatchSchemaTest.php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;

it('stores batch_id, batch_urutan, and batch_total on kasus_tugas', function () {
    $kasus = Kasus::factory()->create();

    $tugas = KasusTugas::create([
        'kasus_id' => $kasus->id,
        'judul' => 'Jurnal Emosi',
        'instruksi' => 'Tulis jurnal harian.',
        'frekuensi' => 'harian',
        'mulai_pada' => '2026-08-10',
        'batas_selesai_pada' => '2026-08-10',
        'batch_id' => 'batch-uuid-contoh',
        'batch_urutan' => 1,
        'batch_total' => 3,
    ]);

    $tugas->refresh();
    expect($tugas->batch_id)->toBe('batch-uuid-contoh');
    expect($tugas->batch_urutan)->toBe(1);
    expect($tugas->batch_total)->toBe(3);
});

it('no longer has a tanggal column on kasus_tugas_submission', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('kasus_tugas_submission', 'tanggal'))->toBeFalse();
});

it('creates a kasus_tugas_submission without a tanggal field at all', function () {
    $kasus = Kasus::factory()->create();
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id]);

    $submission = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Bukti pengerjaan.',
    ]);

    expect($submission->fresh())->not->toBeNull();
});
