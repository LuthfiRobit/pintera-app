<?php
// tests/Feature/Spmb/JalurDaftarActionTest.php

it('stores the chosen lembaga and jalur in session and redirects to spmb.register now that the route exists', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $response = $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $response->assertRedirect(route('spmb.register'));
    $response->assertSessionHas('spmb_pilihan.lembaga_id', $lembaga->id);
    $response->assertSessionHas('spmb_pilihan.jalur_id', $jalur->id);
});

it('404s if the jalur does not belong to the lembaga in the URL', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    [$lembagaDua, , $jalurDua] = buatLembagaDenganGelombangBuka();

    $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembagaSatu->slug, 'jalur' => $jalurDua->id]))
        ->assertNotFound();
});

it('404s if the lembaga has no currently-open gelombang', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = \App\Models\JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);

    $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]))
        ->assertNotFound();
});
