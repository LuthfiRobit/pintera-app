<?php
// tests/Feature/Keuangan/StudentBillingEventsTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Domains\Keuangan\Models\JenisTagihanSasaranKriteria;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Tagihan;

it('generates a tagihan automatically when a new siswa is created and matches an active jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis', 'is_active' => true]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    expect(Tagihan::where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeTrue();
});

it('does not generate a tagihan for a new siswa in a different lembaga', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis', 'is_active' => true]);

    $siswa = Siswa::factory()->create(); // lembaga acak, beda dari $jenisTagihan

    expect(Tagihan::where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id)->exists())->toBeFalse();
});

it('generates a tagihan when a siswa moves into a kelas that matches a kelas-scoped jenis_tagihan', function () {
    $kelasBaru = Kelas::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 150000, 'mode' => 'otomatis', 'is_active' => true, 'lembaga_id' => $kelasBaru->lembaga_id]);
    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'kelas', 'operator' => 'in', 'value' => [$kelasBaru->id]]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $kelasBaru->lembaga_id, 'kelas_id' => null]);
    expect(Tagihan::where('tagihable_id', $siswa->id)->exists())->toBeFalse();

    $siswa->update(['kelas_id' => $kelasBaru->id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeTrue();
});

it('does not fire StudentUpdatedClass when an unrelated field changes', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 150000, 'mode' => 'otomatis', 'is_active' => true]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    Tagihan::query()->delete(); // buang tagihan dari StudentCreated supaya tes ini murni soal update

    $siswa->update(['nama_lengkap' => 'Nama Diperbarui']);

    expect(Tagihan::where('tagihable_id', $siswa->id)->exists())->toBeFalse();
});

it('does not generate a spurious pendaftaran-kategori tagihan when a new siswa is created and a ppdb jenis_tagihan happens to be active with no sasaran', function () {
    $jenisTagihanPpdb = JenisTagihan::factory()->create(['kategori' => 'pendaftaran', 'is_active' => true]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihanPpdb->lembaga_id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('tagihable_type', Siswa::class)->where('jenis_tagihan_id', $jenisTagihanPpdb->id)->exists())->toBeFalse();
});

it('does not generate a spurious daftar_ulang-kategori tagihan when a siswa changes kelas and a ppdb jenis_tagihan happens to be active with no sasaran', function () {
    $jenisTagihanPpdb = JenisTagihan::factory()->create(['kategori' => 'daftar_ulang', 'is_active' => true]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $jenisTagihanPpdb->lembaga_id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihanPpdb->lembaga_id, 'kelas_id' => null]);
    Tagihan::query()->delete(); // buang tagihan dari StudentCreated supaya tes ini murni soal StudentUpdatedClass

    $siswa->update(['kelas_id' => $kelasBaru->id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihanPpdb->id)->exists())->toBeFalse();
});
