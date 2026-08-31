<?php

// tests/Unit/Services/KonselorAllocationResolverTest.php

use App\Domains\Kasus\Services\KonselorAllocationResolver;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatGuruBk(Lembaga $lembaga, array $overrides = []): Guru
{
    return Guru::factory()->create(array_merge([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Guru BK '.fake()->firstName(),
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY',
        'status_aktif' => 'aktif',
    ], $overrides));
}

function buatKaryawanKonselor(Yayasan $yayasan, ?Lembaga $lembaga, array $overrides = []): Karyawan
{
    $jenis = JenisKaryawanMaster::factory()->konselor()->create();

    return Karyawan::factory()->create(array_merge([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga?->id])->id,
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga?->id,
        'jenis_karyawan_id' => $jenis->id,
        'nama' => 'Karyawan '.fake()->firstName(),
        'nik' => fake()->unique()->numerify('################'),
        'status_aktif' => 'aktif',
    ], $overrides));
}

it('returns a guru_bk in the same lembaga as the siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruBk = buatGuruBk($lembaga);
    buatGuruBk($lembaga, ['jenis_ptk' => 'guru_kelas', 'nama' => 'Guru Kelas Biasa']);

    $kandidat = (new KonselorAllocationResolver)->kandidatUntuk($siswa);

    expect($kandidat)->toHaveCount(1);
    expect($kandidat->first()->tipe)->toBe('guru');
    expect($kandidat->first()->model->id)->toBe($guruBk->id);
});

it('falls back to pool karyawan when no guru_bk exists in the lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $pool = buatKaryawanKonselor($yayasan, null);

    $kandidat = (new KonselorAllocationResolver)->kandidatUntuk($siswa);

    expect($kandidat)->toHaveCount(1);
    expect($kandidat->first()->tipe)->toBe('karyawan');
    expect($kandidat->first()->model->id)->toBe($pool->id);
});

it('does not fall back to pool karyawan from a different yayasan', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    buatKaryawanKonselor($yayasanB, null);

    $kandidat = (new KonselorAllocationResolver)->kandidatUntuk($siswa);

    expect($kandidat)->toBeEmpty();
});

it('does not return a dedicated karyawan from a different lembaga in the same yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    buatKaryawanKonselor($yayasan, $lembagaB);

    $kandidat = (new KonselorAllocationResolver)->kandidatUntuk($siswa);

    expect($kandidat)->toBeEmpty();
});

it('excludes guru_bk in an inactive lembaga staff status', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    buatGuruBk($lembaga, ['status_aktif' => 'non_aktif']);

    $kandidat = (new KonselorAllocationResolver)->kandidatUntuk($siswa);

    expect($kandidat)->toBeEmpty();
});

it('excludes a pool karyawan whose jenis_karyawan is not marked is_konselor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisNonKonselor = JenisKaryawanMaster::factory()->create(['is_konselor' => false]);
    buatKaryawanKonselor($yayasan, null, ['jenis_karyawan_id' => $jenisNonKonselor->id]);

    $kandidat = (new KonselorAllocationResolver)->kandidatUntuk($siswa);

    expect($kandidat)->toBeEmpty();
});

it('returns an empty collection (not an exception) when nobody is eligible', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $kandidat = (new KonselorAllocationResolver)->kandidatUntuk($siswa);

    expect($kandidat)->toBeEmpty();
});
