<?php

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatSiswaUntukKartu(): Siswa
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    return Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
}

it('resolveSiswa mengembalikan siswa untuk kode valid dan aktif', function () {
    $siswa = buatSiswaUntukKartu();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-valid-123', 'is_active' => true]);

    $hasil = KartuSiswa::resolveSiswa('kode-valid-123');

    expect($hasil)->not->toBeNull();
    expect($hasil->id)->toBe($siswa->id);
});

it('resolveSiswa mengembalikan null untuk kode yang tidak ditemukan', function () {
    $hasil = KartuSiswa::resolveSiswa('kode-tidak-ada');

    expect($hasil)->toBeNull();
});

it('resolveSiswa mengembalikan null untuk kode yang ditemukan tapi nonaktif', function () {
    $siswa = buatSiswaUntukKartu();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-nonaktif', 'is_active' => false]);

    $hasil = KartuSiswa::resolveSiswa('kode-nonaktif');

    expect($hasil)->toBeNull();
});
