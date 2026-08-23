<?php
// tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Services\JenisTagihanSasaranMatcher;

it('returns every siswa in the lembaga when there is no sasaran grup at all', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    Siswa::factory()->create(); // siswa lembaga lain, tidak boleh ikut

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->sort()->values()->all())
        ->toBe(collect([$siswaSatu->id, $siswaDua->id])->sort()->values()->all());
});

it('matches siswa by AND-ing every kriteria within one grup', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasTujuhA = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    $cocok = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasTujuhA->id, 'jenis_kelamin' => 'L']);
    $bedaKelamin = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasTujuhA->id, 'jenis_kelamin' => 'P']);
    $bedaKelas = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);

    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'kelas', 'operator' => 'in', 'value' => [$kelasTujuhA->id]]);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->all())->toBe([$cocok->id]);
    expect($result->pluck('id')->all())->not->toContain($bedaKelamin->id, $bedaKelas->id);
});

it('OR-s multiple sasaran grup together', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasKhusus = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    $siswaLaki = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    $siswaPerempuanKelasKhusus = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P', 'kelas_id' => $kelasKhusus->id]);
    $siswaPerempuanLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P']);

    $grupLaki = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupLaki->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    $grupKelasKhusus = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupKelasKhusus->id, 'field' => 'kelas', 'operator' => 'in', 'value' => [$kelasKhusus->id]]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    // siswaLaki cocok lewat grupLaki; siswaPerempuanKelasKhusus cocok HANYA lewat grupKelasKhusus
    // (gagal di grupLaki karena jenis_kelamin) — ini yang membuktikan OR antar-grup benar-benar
    // berlaku, bukan cuma satu grup yang kebetulan menjawab semuanya. siswaPerempuanLain tidak
    // cocok grup manapun dan harus tidak ikut.
    expect($result->pluck('id')->sort()->values()->all())
        ->toBe(collect([$siswaLaki->id, $siswaPerempuanKelasKhusus->id])->sort()->values()->all());
    expect($result->pluck('id'))->not->toContain($siswaPerempuanLain->id);
});

it('excludes siswa matching a not_in kriteria', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    $siswaLulus = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'lulus']);

    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'status_siswa', 'operator' => 'not_in', 'value' => ['lulus', 'keluar']]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->all())->toBe([$siswaAktif->id]);
    expect($result->pluck('id')->all())->not->toContain($siswaLulus->id);
});

it('matches tahun_ajaran and tingkat kriteria through the kelas relation', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasEnam = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tingkat' => '6']);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tingkat' => '1']);

    $siswaKelasEnam = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasEnam->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasSatu->id]);

    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'tingkat', 'operator' => 'in', 'value' => ['6']]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->all())->toBe([$siswaKelasEnam->id]);
});

it('treats siswa with kelas_id null as matching a not_in kelas kriteria, agreeing with the PHP path', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasExcluded = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    $siswaTanpaKelas = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => null]);
    $siswaKelasLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);
    $siswaKelasExcluded = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasExcluded->id]);

    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'kelas', 'operator' => 'not_in', 'value' => [$kelasExcluded->id]]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->sort()->values()->all())
        ->toBe(collect([$siswaTanpaKelas->id, $siswaKelasLain->id])->sort()->values()->all());
    expect($result->pluck('id')->all())->not->toContain($siswaKelasExcluded->id);
});

it('siswaMatchesJenisTagihan is true for an empty sasaran and false for a non-matching lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaSendiri = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaLain = Siswa::factory()->create();

    $matcher = new JenisTagihanSasaranMatcher();

    expect($matcher->siswaMatchesJenisTagihan($siswaSendiri, $jenisTagihan))->toBeTrue();
    expect($matcher->siswaMatchesJenisTagihan($siswaLain, $jenisTagihan))->toBeFalse();
});

it('countTotalSiswaPool counts every siswa in the lembaga regardless of any sasaran kriteria', function () {
    $lembaga = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    Siswa::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P']);
    Siswa::factory()->create(['lembaga_id' => $lembagaLain->id, 'jenis_kelamin' => 'L']);

    $total = (new JenisTagihanSasaranMatcher())->countTotalSiswaPool($jenisTagihan);

    expect($total)->toBe(5); // 3 L + 2 P di lembaga yang sama, kriteria diabaikan; siswa lembaga lain tidak dihitung
});
