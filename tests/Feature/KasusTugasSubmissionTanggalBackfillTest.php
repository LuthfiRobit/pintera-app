<?php
// tests/Feature/KasusTugasSubmissionTanggalBackfillTest.php
//
// Proves the 2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian
// migration correctly backfills `tanggal` for pre-existing harian submissions
// that were created before `tanggal` existed (or otherwise left null).

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('backfills tanggal to DATE(created_at) for harian submissions left with a null tanggal', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $tugas = KasusTugas::factory()->create([
        'kasus_id' => $kasus->id,
        'frekuensi' => 'harian',
        'mulai_pada' => now()->subDays(10)->toDateString(),
        'batas_selesai_pada' => now()->addDays(10)->toDateString(),
    ]);

    $submission = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Submisi lama sebelum kolom tanggal ada.',
        'status_review' => 'diterima',
        'tanggal' => null,
        'created_at' => now()->subDays(5),
    ]);
    // created_at is guarded by timestamps() default behavior on create(); force it.
    $submission->forceFill(['created_at' => now()->subDays(5)])->save();

    expect($submission->fresh()->tanggal)->toBeNull();

    $migration = include base_path('database/migrations/2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php');
    $migration->up();

    $submission->refresh();
    expect($submission->tanggal)->not->toBeNull();
    expect($submission->tanggal->toDateString())->toBe($submission->created_at->toDateString());
});

it('does not touch tanggal for non-harian tugas submissions', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $tugas = KasusTugas::factory()->create([
        'kasus_id' => $kasus->id,
        'frekuensi' => 'sekali',
    ]);

    $submission = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Submisi sekali, tidak ada tanggal per hari.',
        'status_review' => 'diterima',
        'tanggal' => null,
    ]);

    $migration = include base_path('database/migrations/2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php');
    $migration->up();

    expect($submission->fresh()->tanggal)->toBeNull();
});
