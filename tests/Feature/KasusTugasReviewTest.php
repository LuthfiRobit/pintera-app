<?php
// tests/Feature/KasusTugasReviewTest.php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Notification;

it('marks a submission revisi_diminta with a catatan and moves tugas status to revisi', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);

    Notification::fake();

    $this->actingAs($konselorUser)->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
        'status_review' => 'revisi_diminta',
        'catatan_revisi' => 'Tolong lebih detail.',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($submission->refresh()->status_review)->toBe('revisi_diminta');
    expect($submission->catatan_revisi)->toBe('Tolong lebih detail.');
    expect($tugas->refresh()->status->value)->toBe('revisi');
});

it('marks a submission diterima without changing tugas status', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);

    $this->actingAs($konselorUser)->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
        'status_review' => 'diterima',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($submission->refresh()->status_review)->toBe('diterima');
    expect($tugas->refresh()->status->value)->toBe('dikerjakan');
});

it('lets the konselor manually mark a tugas selesai regardless of submission completeness', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan', 'frekuensi' => 'harian']);

    $this->actingAs($konselorUser)->patch(route('kasus.tugas.selesai', [$kasus, $tugas]))
        ->assertRedirect(route('kasus.show', $kasus));

    expect($tugas->refresh()->status->value)->toBe('selesai');
});

it('403s a konselor who is not assigned from reviewing a submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBk($lembaga);
    [, $unrelatedKonselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id]);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);

    $this->actingAs($unrelatedKonselorUser)->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
        'status_review' => 'diterima',
    ])->assertForbidden();
});
