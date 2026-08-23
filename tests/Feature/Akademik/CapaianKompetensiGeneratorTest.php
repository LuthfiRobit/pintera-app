<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanSiswaMapelSemester(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    return compact('siswa', 'mapel', 'semester', 'asesmen');
}

it('generates a positive sentence when the highest-scoring TP meets its kktp_minimal', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'operasi bilangan bulat', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBe('Menunjukkan penguasaan sangat baik dalam operasi bilangan bulat.');
    expect($hasil['terendah'])->toBeNull();
});

it('generates a needs-guidance sentence when the lowest-scoring TP is below its kktp_minimal', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'relasi dan fungsi', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 60]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBeNull();
    expect($hasil['terendah'])->toBe('Perlu bimbingan dan pendampingan dalam relasi dan fungsi.');
});

it('generates both sentences when there are at least two TP spanning both conditions', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponenTinggi = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'TP kuat', 'kktp_minimal' => 75]);
    $komponenRendah = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'TP lemah', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenTinggi->id, 'nilai_angka' => 95]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenRendah->id, 'nilai_angka' => 50]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBe('Menunjukkan penguasaan sangat baik dalam TP kuat.');
    expect($hasil['terendah'])->toBe('Perlu bimbingan dan pendampingan dalam TP lemah.');
});

it('returns null for both when no TP has any nilai at all', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester] = siapkanSiswaMapelSemester();
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBeNull();
    expect($hasil['terendah'])->toBeNull();
});

it('falls back to a default 75 threshold when kktp_minimal is null', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'TP tanpa ambang eksplisit', 'kktp_minimal' => null]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBe('Menunjukkan penguasaan sangat baik dalam TP tanpa ambang eksplisit.');
});
