<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanSiswa;
use App\Models\Siswa;

it('stores a per-siswa nominal override for a jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $siswa = Siswa::factory()->create();

    NominalTagihanSiswa::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'siswa_id' => $siswa->id,
        'nominal' => 300000,
    ]);

    $override = NominalTagihanSiswa::where('jenis_tagihan_id', $jenisTagihan->id)->where('siswa_id', $siswa->id)->first();
    expect((float) $override->nominal)->toBe(300000.0);
});

it('rejects a duplicate override for the same jenis_tagihan and siswa pair', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $siswa = Siswa::factory()->create();

    NominalTagihanSiswa::create(['jenis_tagihan_id' => $jenisTagihan->id, 'siswa_id' => $siswa->id, 'nominal' => 300000]);

    expect(fn () => NominalTagihanSiswa::create(['jenis_tagihan_id' => $jenisTagihan->id, 'siswa_id' => $siswa->id, 'nominal' => 400000]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
