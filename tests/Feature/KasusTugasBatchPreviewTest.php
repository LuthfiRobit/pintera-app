<?php
// tests/Feature/KasusTugasBatchPreviewTest.php

use App\Models\Lembaga;
use App\Models\Yayasan;

it('returns a JSON preview without creating any kasus_tugas row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $response = $this->actingAs($konselorUser)->postJson(route('kasus.tugas.preview', $kasus), [
        'judul' => 'Jurnal Emosi', 'instruksi' => 'Tulis jurnal harian.', 'frekuensi' => 'mingguan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-05',
    ]);

    $response->assertOk();
    $response->assertJson(['frekuensi_akhir' => 'harian', 'jumlah_baris' => 5]);
    expect(\App\Domains\Kasus\Models\KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('returns the exact same row dates the real submit would create', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $payload = [
        'judul' => 'Jurnal Emosi', 'instruksi' => 'Tulis jurnal harian.', 'frekuensi' => 'harian',
        'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-12',
    ];

    $preview = $this->actingAs($konselorUser)->postJson(route('kasus.tugas.preview', $kasus), $payload);
    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), $payload);

    $barisAsli = \App\Domains\Kasus\Models\KasusTugas::where('kasus_id', $kasus->id)->orderBy('batch_urutan')->pluck('mulai_pada')->map->toDateString()->all();
    $barisPreview = collect($preview->json('baris'))->pluck('mulai_pada')->all();

    expect($barisPreview)->toBe($barisAsli);
});

it('returns 200 with frekuensi_akhir bulanan for a bulanan-eligible range with an empty tanggal_pengumpulan_bulanan (Finding 1 regression)', function () {
    // Regression test: the preview endpoint must not require tanggal_pengumpulan_bulanan just
    // to report that the real submit would need it — the Alpine form always posts an empty
    // string for it before the counselor has picked a due day, and a 422 here would keep the
    // due-day <select> permanently hidden (x-show="frekuensiAkhir === 'bulanan'").
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $response = $this->actingAs($konselorUser)->postJson(route('kasus.tugas.preview', $kasus), [
        'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
        'tanggal_pengumpulan_bulanan' => '',
    ]);

    $response->assertOk();
    $response->assertJson(['frekuensi_akhir' => 'bulanan']);
});

it('returns 200 when judul and instruksi are absent from the payload (Finding 3 regression)', function () {
    // Regression test: the preview endpoint must not require judul/instruksi at all — a
    // counselor who sets dates before typing a title must still see the preview.
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $response = $this->actingAs($konselorUser)->postJson(route('kasus.tugas.preview', $kasus), [
        'judul' => '', 'instruksi' => '', 'frekuensi' => 'harian',
        'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-12',
    ]);

    $response->assertOk();
    $response->assertJson(['frekuensi_akhir' => 'harian', 'jumlah_baris' => 3]);
});

it('403s a POST to preview against an already-selesai kasus (guards match store, Finding 5)', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $kasus->update(['status' => \App\Domains\Kasus\Enums\StatusKasus::Selesai]);

    $this->actingAs($konselorUser)->postJson(route('kasus.tugas.preview', $kasus), [
        'frekuensi' => 'sekali', 'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-01',
    ])->assertForbidden();
});

it('403s a user who is not the assigned konselor from previewing', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $strangerUser = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($strangerUser)->postJson(route('kasus.tugas.preview', $kasus), [
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'sekali',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-01',
    ])->assertForbidden();
});
