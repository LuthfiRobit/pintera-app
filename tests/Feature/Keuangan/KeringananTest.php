<?php
// tests/Feature/Keuangan/KeringananTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\JenisTagihanKeringanan;
use App\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaKeringanan;
use App\Models\User;

it('lets a jenis_tagihan define its own discount rule for a kategori_keringanan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);

    $rule = JenisTagihanKeringanan::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'kategori_keringanan_id' => $kategori->id,
        'tipe_potongan' => 'persen',
        'nilai' => 50,
    ]);

    expect($rule->kategoriKeringanan->nama)->toBe('Anak Pegawai');
    expect((float) $rule->nilai)->toBe(50.0);
});

it('marks a siswa as having a kategori_keringanan without storing a discount value on the pivot', function () {
    $siswa = Siswa::factory()->create();
    $kategori = KategoriKeringanan::create(['lembaga_id' => $siswa->lembaga_id, 'nama' => 'Anak Pegawai']);

    $siswaKeringanan = SiswaKeringanan::create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->toDateString(),
    ]);

    expect($siswaKeringanan->berlaku_sampai)->toBeNull();
    expect($siswaKeringanan->kategoriKeringanan->nama)->toBe('Anak Pegawai');
});

it('scopes kategori_keringanan queries to the acting lembaga via BelongsToTenant', function () {
    $lembagaSendiri = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();

    KategoriKeringanan::create(['lembaga_id' => $lembagaSendiri->id, 'nama' => 'Anak Pegawai']);
    KategoriKeringanan::create(['lembaga_id' => $lembagaLain->id, 'nama' => 'Beasiswa Lembaga Lain']);

    $manager = User::factory()->create(['lembaga_id' => $lembagaSendiri->id]);
    $this->actingAs($manager);

    expect(KategoriKeringanan::count())->toBe(1);
    expect(KategoriKeringanan::first()->nama)->toBe('Anak Pegawai');
});
