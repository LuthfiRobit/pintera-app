<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Models\Siswa;

it('TagihanBillingGenerator fills tagihan.person_id from siswa.person_id directly', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    app(TagihanBillingGenerator::class)->generateForSiswa($siswa, $jenisTagihan, 'manual');

    $tagihan = $siswa->tagihan()->where('jenis_tagihan_id', $jenisTagihan->id)->first();
    expect($tagihan->person_id)->toBe($siswa->person_id);
});

it('throws hard, instead of creating a tagihan with a null person_id, when siswa.person_id is null', function () {
    $siswa = Siswa::factory()->create();
    // siswa.person_id is NOT NULL at the DB level (identity-v1), so persisting a null
    // is impossible. Simulate corrupt in-memory data instead: override the attribute
    // without saving, leaving the real persisted row (and its relations) intact so the
    // generator's earlier resolver logic still has valid data to work with.
    $siswa->person_id = null;
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    expect(fn () => app(TagihanBillingGenerator::class)->generateForSiswa($siswa, $jenisTagihan, 'manual'))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseMissing('tagihan', ['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id]);
});
