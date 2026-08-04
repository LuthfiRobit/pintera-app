<?php
// tests/Feature/KasusTugasReviewTest.php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

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

it('notifies the siswa on revisi_diminta even when a yayasan-scoped konselor has an active-lembaga session filter pointing at a lembaga other than the kasus\'s', function () {
    // Reproduces the Finding 7 bug: KasusTugasSubmissionController::review() lazily loads
    // $kasusTugasSubmission->siswa (a BelongsToTenant model) WITHOUT bypassing TenantScope.
    // For any yayasan-scoped acting user (widestScopeLevel === 'yayasan', e.g. karyawan_pool),
    // TenantScope filters every tenant-scoped table by session('active_lembaga_id') when set.
    // Here the konselor's own Karyawan row lives in $lembagaLain (matching the session filter
    // so authorization succeeds normally), while the kasus/siswa live in a DIFFERENT lembaga
    // ($lembaga) — exactly the cross-lembaga situation a yayasan-wide konselor legitimately
    // works in. Before the fix, the unscoped siswa lookup silently resolves null and no
    // notification is sent; after the fix, withoutGlobalScope(TenantScope::class) finds it.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $siswaRole = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaRole->givePermissionTo(['kasus.view']);
    $siswaUser->assignRole('siswa');
    $siswa->update(['user_id' => $siswaUser->id]);

    $konselorUser = User::factory()->create(['lembaga_id' => null]);
    $konselorRole = Role::firstOrCreate(['name' => 'karyawan_pool', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $konselorRole->givePermissionTo(['kasus.view']);
    $konselorUser->assignRole('karyawan_pool');
    $jenis = \App\Models\JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = \App\Models\Karyawan::withoutGlobalScopes()->create([
        'user_id' => $konselorUser->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaLain->id,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Konselor Lintas Lembaga',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => \App\Enums\StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id]);

    Notification::fake();

    $this->actingAs($konselorUser)
        ->withSession(['active_lembaga_id' => $lembagaLain->id])
        ->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
            'status_review' => 'revisi_diminta',
            'catatan_revisi' => 'Tolong diperbaiki.',
        ])->assertRedirect(route('kasus.show', $kasus));

    Notification::assertSentTo($siswaUser, \App\Notifications\SubmissionRevisiNotification::class);
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
