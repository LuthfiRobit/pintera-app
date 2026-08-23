<?php
// tests/Feature/Keuangan/TagihanNominalResolverTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\NominalTagihanSiswa;
use App\Models\Siswa;
use App\Models\SiswaKeringanan;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanNominalResolver;

function buatResolver(): TagihanNominalResolver
{
    return new TagihanNominalResolver(new JenisTagihanSasaranMatcher());
}

it('falls back to default_amount when nothing else matches', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 250000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(250000.0);
    expect($result['discount_amount'])->toBe(0.0);
    expect($result['discount_type'])->toBeNull();
});

it('uses the first matching tarif grup nominal over default_amount', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 250000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id, 'jenis_kelamin' => 'L']);

    $grupTarif = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'tarif', 'nominal' => 300000]);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupTarif->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(300000.0);
});

it('uses nominal_tagihan_siswa override above every other source', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 250000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id, 'jenis_kelamin' => 'L']);

    $grupTarif = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'tarif', 'nominal' => 300000]);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupTarif->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    NominalTagihanSiswa::create(['jenis_tagihan_id' => $jenisTagihan->id, 'siswa_id' => $siswa->id, 'nominal' => 100000]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(100000.0);
});

it('computes a fixed discount amount directly from nilai', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 75000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(75000.0);
    expect($result['discount_type'])->toBe('fixed');
});

it('computes a persen discount as a percentage of the resolved nominal', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Beasiswa']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'persen', 'nilai' => 10]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(50000.0);
    expect($result['discount_type'])->toBe('persen');
});

it('picks the largest computed rupiah discount when multiple keringanan rules match', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    // Raw nilai comparison (60 vs 200000) disagrees with computed-amount comparison (300000 vs 200000),
    // so this test only passes if the resolver compares computed Rupiah amounts, not raw nilai.
    $kategoriPersen = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Beasiswa']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriPersen->id, 'tipe_potongan' => 'persen', 'nilai' => 60]); // = 300000
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriPersen->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $kategoriFixed = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriFixed->id, 'tipe_potongan' => 'fixed', 'nilai' => 200000]); // = 200000
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriFixed->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(300000.0);
    expect($result['discount_type'])->toBe('persen');
});

it('ignores a keringanan whose berlaku_sampai has already passed', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 75000]);
    SiswaKeringanan::create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subMonths(2)->toDateString(),
        'berlaku_sampai' => now()->subMonth()->toDateString(),
    ]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(0.0);
});
