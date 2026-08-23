<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanAsesmenUntukNilaiSiswaTest(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifAkhirSemester, 'judul' => 'UAS', 'tanggal' => '2026-12-10',
    ]);

    return compact('siswa', 'asesmen', 'komponen');
}

it('stores a nilai_angka for a siswa on a specific komponen of an asesmen', function () {
    ['siswa' => $siswa, 'asesmen' => $asesmen, 'komponen' => $komponen] = siapkanAsesmenUntukNilaiSiswaTest();

    $nilai = NilaiSiswa::create([
        'asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => 88, 'predikat' => null, 'catatan' => null,
    ]);

    expect($nilai->fresh()->komponenPenilaian->id)->toBe($komponen->id);
    expect($nilai->fresh()->nilai_angka)->toBe(88);
});

it('allows nilai_angka to be null with only predikat/catatan for narrative-style scoring', function () {
    ['siswa' => $siswa, 'asesmen' => $asesmen, 'komponen' => $komponen] = siapkanAsesmenUntukNilaiSiswaTest();

    $nilai = NilaiSiswa::create([
        'asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => null, 'predikat' => 'Berkembang Sesuai Harapan', 'catatan' => 'Aktif berinteraksi dengan teman sebaya',
    ]);

    expect($nilai->fresh()->nilai_angka)->toBeNull();
    expect($nilai->fresh()->predikat)->toBe('Berkembang Sesuai Harapan');
});

it('enforces one nilai row per siswa per komponen per asesmen', function () {
    ['siswa' => $siswa, 'asesmen' => $asesmen, 'komponen' => $komponen] = siapkanAsesmenUntukNilaiSiswaTest();

    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);

    expect(fn () => NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
