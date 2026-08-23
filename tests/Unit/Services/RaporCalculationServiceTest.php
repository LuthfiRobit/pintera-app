<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('computes a weighted average per siswa per mapel using komponen bobot', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $komponenBerat = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 70]);
    $komponenRingan = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 30]);

    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenBerat->id, 'nilai_angka' => 80]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenRingan->id, 'nilai_angka' => 90]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    // (80*70 + 90*30) / 100 = 83.0
    expect($rekap['rekapNilai'][$siswa->id][$mapel->id])->toBe(83.0);
    expect($rekap['classAvg'])->toBe(83.0);
    expect($rekap['highestScore'])->toBe(83.0);
    expect($rekap['siswaList']->pluck('id')->all())->toBe([$siswa->id]);
    expect($rekap['mapelList']->pluck('id')->all())->toBe([$mapel->id]);
});

it('returns null score for a siswa with no nilai on that mapel', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaTanpaNilai = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    expect($rekap['rekapNilai'][$siswaTanpaNilai->id][$mapel->id])->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('returns empty structure when kelas has no asesmen in the semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('returns no data when kelas and semester belong to different lembaga, even when called directly with a mismatched pair', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    Asesmen::factory()->create(['kelas_id' => $kelasA->id, 'mata_pelajaran_id' => $mapelA->id, 'semester_id' => $semesterA->id]);

    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelasA, $semesterB);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['rekapNilai'])->toBeEmpty();
});
