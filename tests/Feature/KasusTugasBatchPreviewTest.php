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
    expect(\App\Models\KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
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

    $barisAsli = \App\Models\KasusTugas::where('kasus_id', $kasus->id)->orderBy('batch_urutan')->pluck('mulai_pada')->map->toDateString()->all();
    $barisPreview = collect($preview->json('baris'))->pluck('mulai_pada')->all();

    expect($barisPreview)->toBe($barisAsli);
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
