<?php

use App\Domains\Kasus\Actions\Submission\ReviewSubmissionAction;
use App\Domains\Kasus\DataTransferObjects\ReviewSubmissionData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requesting revisi_diminta moves the tugas status to revisi', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id]);

    $result = (new ReviewSubmissionAction)->execute($tugas, $submission, new ReviewSubmissionData(
        statusReview: 'revisi_diminta',
        catatanRevisi: 'Foto kurang jelas, tolong ulangi.',
    ));

    expect($result->status_review)->toBe('revisi_diminta')
        ->and($tugas->fresh()->status->value)->toBe('revisi');
});

it('accepting a submission does not change the tugas status', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id]);

    (new ReviewSubmissionAction)->execute($tugas, $submission, new ReviewSubmissionData(
        statusReview: 'diterima',
        catatanRevisi: null,
    ));

    expect($tugas->fresh()->status->value)->toBe('dikerjakan');
});
