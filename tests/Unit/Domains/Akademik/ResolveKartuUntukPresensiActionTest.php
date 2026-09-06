<?php

use App\Domains\Akademik\Actions\KartuSiswa\ResolveKartuUntukPresensiAction;
use App\Domains\Akademik\Exceptions\KartuKelasMismatchException;
use App\Domains\Akademik\Exceptions\KartuLembagaMismatchException;
use App\Domains\Akademik\Exceptions\KartuTidakValidException;
use App\Domains\Akademik\Models\KartuSiswa;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatSesiDenganKelas(Kelas $kelas): SesiPembelajaran
{
    return SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id]);
}

it('mengembalikan siswa kalau kartu valid, satu kelas, dan satu lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-ok', 'is_active' => true]);
    $sesi = buatSesiDenganKelas($kelas);

    $hasil = (new ResolveKartuUntukPresensiAction)->execute('kode-ok', $sesi, $lembaga->id);

    expect($hasil->id)->toBe($siswa->id);
});

it('melempar KartuTidakValidException kalau kode tidak ditemukan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $sesi = buatSesiDenganKelas($kelas);

    expect(fn () => (new ResolveKartuUntukPresensiAction)->execute('kode-tidak-ada', $sesi, $lembaga->id))
        ->toThrow(KartuTidakValidException::class);
});

it('melempar KartuKelasMismatchException kalau siswa beda kelas dari sesi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasSiswa = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $kelasSesi = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasSiswa->id]);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-beda-kelas', 'is_active' => true]);
    $sesi = buatSesiDenganKelas($kelasSesi);

    expect(fn () => (new ResolveKartuUntukPresensiAction)->execute('kode-beda-kelas', $sesi, $lembaga->id))
        ->toThrow(KartuKelasMismatchException::class);
});

it('melempar KartuLembagaMismatchException kalau siswa beda lembaga dari guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSiswa = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSiswa->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaSiswa->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaSiswa->id, 'kelas_id' => $kelas->id]);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-beda-lembaga', 'is_active' => true]);
    $sesi = buatSesiDenganKelas($kelas);

    expect(fn () => (new ResolveKartuUntukPresensiAction)->execute('kode-beda-lembaga', $sesi, $lembagaLain->id))
        ->toThrow(KartuLembagaMismatchException::class);
});
