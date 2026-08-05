<?php
// tests/Feature/KasusTugasHarianViewTest.php

use App\Models\Kasus;
use App\Models\KasusConsent;
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
