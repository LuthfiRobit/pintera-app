<?php
// tests/Feature/KasusTugasBatchViewTest.php

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Support\Str;

it('shows the single-definition tugas form fields, not the old multi-row form', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('name="judul"', false);
    $response->assertSee('name="tanggal_mulai"', false);
    $response->assertSee('name="tanggal_selesai"', false);
    // 'Tambah Baris' also legitimately appears in the unrelated sesi tab's own
    // multi-row form, so assert against the old tugas form's unique Alpine
    // field binding instead of the ambiguous button label.
    $response->assertDontSee('row.judul', false);
});

it('groups tugas rows from the same batch under one header showing progress', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $batchId = (string) Str::uuid();
    foreach ([1, 2, 3] as $urutan) {
        KasusTugas::factory()->create([
            'kasus_id' => $kasus->id, 'judul' => 'Jurnal Emosi', 'frekuensi' => 'harian',
            'batch_id' => $batchId, 'batch_urutan' => $urutan, 'batch_total' => 3,
            'mulai_pada' => now()->addDays($urutan), 'batas_selesai_pada' => now()->addDays($urutan),
        ]);
    }

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSeeInOrder(['Jurnal Emosi', 'Hari 1 dari 3', 'Hari 2 dari 3', 'Hari 3 dari 3']);
});

it('does not render the removed per-tanggal checklist markup anywhere', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'frekuensi' => 'harian']);

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertDontSee('Checklist Harian');
});
