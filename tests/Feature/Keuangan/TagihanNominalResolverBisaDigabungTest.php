<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Lembaga;
use App\Models\Siswa;

it('still picks only the largest discount when all matching categories are non-combinable (regression)', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000]);

    $kategoriKecil = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    $kategoriBesar = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriKecil->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriBesar->id, 'tipe_potongan' => 'fixed', 'nilai' => 150000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriKecil->id, 'berlaku_dari' => now()->subDay()->toDateString()]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriBesar->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $resolver = new TagihanNominalResolver(new JenisTagihanSasaranMatcher);
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(150000.0); // hanya yang terbesar, tidak dijumlah
});

it('sums combinable discounts on top of the best non-combinable one', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000]);

    $kategoriUtama = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    $kategoriTambahan = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => true]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'tipe_potongan' => 'fixed', 'nilai' => 150000]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'berlaku_dari' => now()->subDay()->toDateString()]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $resolver = new TagihanNominalResolver(new JenisTagihanSasaranMatcher);
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(200000.0); // 150000 (terbesar non-combinable) + 50000 (combinable)
});

it('clamps total discount to the nominal, never producing a negative net_amount', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 100000]);

    $kategoriUtama = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    $kategoriTambahan = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => true]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'tipe_potongan' => 'fixed', 'nilai' => 80000]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'tipe_potongan' => 'fixed', 'nilai' => 80000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'berlaku_dari' => now()->subDay()->toDateString()]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $resolver = new TagihanNominalResolver(new JenisTagihanSasaranMatcher);
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(100000.0); // 80000+80000=160000 di-clamp ke nominal 100000
});
