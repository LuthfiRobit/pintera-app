<?php

use App\Domains\Akademik\Actions\Rapor\GenerateNarasiPerkembanganAction;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanKelasSemesterUntukNarasi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('kelas', 'semester', 'siswa');
}

it('concatenates narasi across every mapel the kelas has an asesmen for', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'siswa' => $siswa] = siapkanKelasSemesterUntukNarasi();

    $matematika = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'nama' => 'Matematika']);
    $asesmenMtk = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $matematika->id, 'semester_id' => $semester->id]);
    $komponenMtk = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $matematika->id, 'semester_id' => $semester->id, 'deskripsi' => 'operasi bilangan bulat', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmenMtk->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenMtk->id, 'nilai_angka' => 90]);

    $ipa = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'nama' => 'IPA']);
    $asesmenIpa = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $ipa->id, 'semester_id' => $semester->id]);
    $komponenIpa = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $ipa->id, 'semester_id' => $semester->id, 'deskripsi' => 'siklus air', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmenIpa->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenIpa->id, 'nilai_angka' => 50]);

    $narasi = app(GenerateNarasiPerkembanganAction::class)->execute($siswa, $kelas, $semester);

    expect($narasi)->toContain('Menunjukkan penguasaan sangat baik dalam operasi bilangan bulat.');
    expect($narasi)->toContain('Perlu bimbingan dan pendampingan dalam siklus air.');
});

it('returns an empty string when the kelas has no asesmen at all in the semester', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'siswa' => $siswa] = siapkanKelasSemesterUntukNarasi();

    $narasi = app(GenerateNarasiPerkembanganAction::class)->execute($siswa, $kelas, $semester);

    expect($narasi)->toBe('');
});
