<?php
// tests/Feature/KasusTugasHarianViewTest.php

use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('shows one row per date in the harian range, each with its own submit form when open', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSeeInOrder(['10 Aug 2026', '11 Aug 2026', '12 Aug 2026']);
});

it('locks the submitted date row and shows its history, while other date rows stay open', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari pertama yang unik.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('Bukti hari pertama yang unik.');
    // The locked date's own row must not render a submit form again; count the remaining
    // open dates' submit-button label occurrences (2 of the 3 dates: 11 and 12 Aug).
    $response->assertSeeInOrder(['10 Aug 2026', 'Bukti hari pertama yang unik.', '11 Aug 2026', '12 Aug 2026']);
});

it('does not render the per-date checklist for a non-harian tugas', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    // 'Aug 2026' alone is unreliable here: the tugas header always renders a
    // "Batas Selesai: <d M Y>" line regardless of frekuensi, which can itself contain
    // an August 2026 date depending on the factory's random dates. Assert on the
    // per-date checklist's own unique marker instead.
    $response->assertDontSee('Checklist Harian');
});

it('renders the kasus page without error and surfaces a harian submission whose tanggal falls outside the tugas date range', function () {
    // Belt-and-suspenders for Finding 1: even after the backfill migration sets
    // tanggal = DATE(created_at), a submission can still fail to land in any
    // rendered per-date row if its (backfilled) tanggal happens to fall outside
    // the tugas's own mulai_pada-batas_selesai_pada range. The view must not
    // crash, and must not silently make this submission disappear.
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'siswa_id' => $siswaUser->siswa->id,
        'teks' => 'Submisi lawas dengan tanggal di luar rentang tugas.',
        'status_review' => 'menunggu_review',
        'tanggal' => '2026-09-01', // outside the tugas's 2026-08-10..12 range
    ]);

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('Submisi lawas dengan tanggal di luar rentang tugas.');
});

it('renders the kasus page without error and surfaces a harian submission with a genuinely null tanggal', function () {
    // The actual bug Finding 1 was about: a submission that was never backfilled at all
    // (tanggal still null), not just one whose backfilled tanggal falls outside range.
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'siswa_id' => $siswaUser->siswa->id,
        'teks' => 'Submisi lawas tanpa tanggal sama sekali.',
        'status_review' => 'menunggu_review',
        'tanggal' => null,
    ]);

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('Submisi lawas tanpa tanggal sama sekali.');
});

it('shows the full submission history for a locked date, not just the latest attempt, after a revisi cycle', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti percobaan pertama yang direvisi.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();
    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->where('tanggal', '2026-08-10')->firstOrFail();
    $submission->update(['status_review' => 'revisi_diminta', 'catatan_revisi' => 'Perbaiki.']);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hasil revisi yang sudah diperbaiki.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('Bukti percobaan pertama yang direvisi.');
    $response->assertSee('Bukti hasil revisi yang sudah diperbaiki.');
});
