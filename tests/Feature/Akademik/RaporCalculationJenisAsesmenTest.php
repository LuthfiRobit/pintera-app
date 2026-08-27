<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

function siapkanSubjekJenisAsesmen(): array
{
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    return compact('mapel', 'lembaga', 'semester', 'kelas', 'guru', 'siswa');
}

function buatAsesmenDenganNilai(string $jenis, array $ctx, int $nilaiAngka): Asesmen
{
    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $ctx['mapel']->id, 'semester_id' => $ctx['semester']->id,
        'lembaga_id' => $ctx['lembaga'], 'assessment_type' => 'numeric',
    ]);
    $asesmen = Asesmen::factory()->create([
        'guru_id' => $ctx['guru']->id, 'kelas_id' => $ctx['kelas']->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $ctx['mapel']->id, 'semester_id' => $ctx['semester']->id, 'jenis' => $jenis,
    ]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $ctx['siswa']->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => $nilaiAngka]);

    return $asesmen;
}

it('excludes DiagnostikKognitif entirely from rekap rapor', function () {
    $ctx = siapkanSubjekJenisAsesmen();
    buatAsesmenDenganNilai(JenisAsesmen::DiagnostikKognitif->value, $ctx, 90);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id] ?? null)->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('excludes DiagnostikNonKognitif entirely from rekap rapor', function () {
    $ctx = siapkanSubjekJenisAsesmen();
    buatAsesmenDenganNilai(JenisAsesmen::DiagnostikNonKognitif->value, $ctx, 90);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id] ?? null)->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('excludes Formatif entirely from rekap rapor', function () {
    $ctx = siapkanSubjekJenisAsesmen();
    buatAsesmenDenganNilai(JenisAsesmen::Formatif->value, $ctx, 90);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id] ?? null)->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('keeps rekap at the Sumatif value even when Formatif and Diagnostik nilai exist for the same siswa+subjek+semester', function () {
    $ctx = siapkanSubjekJenisAsesmen();

    $asesmenSumatif = buatAsesmenDenganNilai(JenisAsesmen::SumatifLingkupMateri->value, $ctx, 88);
    $asesmenFormatif = buatAsesmenDenganNilai(JenisAsesmen::Formatif->value, $ctx, 100);
    $asesmenDiagnostik = buatAsesmenDenganNilai(JenisAsesmen::DiagnostikKognitif->value, $ctx, 100);

    // WAJIB dibuktikan dulu: data non-rapor benar-benar tersimpan dgn nilai yang benar --
    // supaya exclusion di bawah ini terbukti krn FILTER, bukan krn datanya gagal dibuat.
    expect(Asesmen::where('id', $asesmenFormatif->id)->where('jenis', JenisAsesmen::Formatif)->exists())->toBeTrue();
    expect(NilaiSiswa::where('asesmen_id', $asesmenFormatif->id)->where('siswa_id', $ctx['siswa']->id)->first()->nilai_angka)->toBe(100);
    expect(Asesmen::where('id', $asesmenDiagnostik->id)->where('jenis', JenisAsesmen::DiagnostikKognitif)->exists())->toBeTrue();
    expect(NilaiSiswa::where('asesmen_id', $asesmenDiagnostik->id)->where('siswa_id', $ctx['siswa']->id)->first()->nilai_angka)->toBe(100);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);
    $sel = $rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id];

    expect($sel->label)->toBe('88');
    expect($rekap['classAvg'])->toBe(88.0);
});
