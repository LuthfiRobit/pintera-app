<?php
// tests/Feature/KasusSoftDeleteSchemaTest.php

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Domains\Kasus\Models\KasusSesi;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;

it('soft-deletes a kasus instead of removing the row', function () {
    $kasus = Kasus::factory()->create();

    $kasus->delete();

    expect(Kasus::find($kasus->id))->toBeNull();
    $trashed = Kasus::withTrashed()->find($kasus->id);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();
});

it('gives every kasus child model a working deleted_at column', function () {
    $kasus = Kasus::factory()->create();
    $sesi = KasusSesi::factory()->create(['kasus_id' => $kasus->id]);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id]);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);
    $evaluasi = KasusEvaluasi::factory()->create(['kasus_id' => $kasus->id]);
    $consent = KasusConsent::factory()->create(['kasus_id' => $kasus->id]);

    $sesi->delete();
    $tugas->delete();
    $submission->delete();
    $evaluasi->delete();
    $consent->delete();

    expect(KasusSesi::find($sesi->id))->toBeNull();
    expect(KasusSesi::withTrashed()->find($sesi->id)->deleted_at)->not->toBeNull();
    expect(KasusTugas::find($tugas->id))->toBeNull();
    expect(KasusTugas::withTrashed()->find($tugas->id)->deleted_at)->not->toBeNull();
    expect(KasusTugasSubmission::find($submission->id))->toBeNull();
    expect(KasusTugasSubmission::withTrashed()->find($submission->id)->deleted_at)->not->toBeNull();
    expect(KasusEvaluasi::find($evaluasi->id))->toBeNull();
    expect(KasusEvaluasi::withTrashed()->find($evaluasi->id)->deleted_at)->not->toBeNull();
    expect(KasusConsent::find($consent->id))->toBeNull();
    expect(KasusConsent::withTrashed()->find($consent->id)->deleted_at)->not->toBeNull();
});
