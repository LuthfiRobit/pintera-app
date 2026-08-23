<?php
// tests/Feature/Keuangan/ProsesTagihanCommandTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Tagihan;

it('generates tagihan for the given jenis_tagihan_id and reports the count', function () {
    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 200000]);

    $this->artisan('billing:proses', ['jenis_tagihan_id' => $jenisTagihan->id])
        ->expectsOutputToContain('1 tagihan dibuat')
        ->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
});

it('fails gracefully when the jenis_tagihan_id does not exist', function () {
    $this->artisan('billing:proses', ['jenis_tagihan_id' => 999999])
        ->expectsOutputToContain('tidak ditemukan')
        ->assertExitCode(1);
});

it('fails gracefully with a clear message for a ppdb-kategori jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'daftar_ulang']);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $this->artisan('billing:proses', ['jenis_tagihan_id' => $jenisTagihan->id])
        ->expectsOutputToContain('tidak bisa diproses lewat billing engine')
        ->assertExitCode(1);

    expect(Tagihan::count())->toBe(0);
});
