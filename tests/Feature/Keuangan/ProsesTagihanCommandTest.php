<?php
// tests/Feature/Keuangan/ProsesTagihanCommandTest.php

use App\Models\JenisTagihan;
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
