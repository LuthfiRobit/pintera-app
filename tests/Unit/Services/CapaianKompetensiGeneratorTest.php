<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates a positive narasi from Sumatif nilai only, ignoring a lower Formatif nilai for the same siswa+subjek+semester', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric', 'kktp_minimal' => 75,
    ]);

    $asesmenSumatif = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => 'sumatif_lingkup_materi',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenSumatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    $asesmenFormatif = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => 'formatif',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenFormatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 40]);

    // WAJIB dibuktikan dulu: data Formatif benar-benar tersimpan sebelum assert exclusion.
    expect(Asesmen::where('id', $asesmenFormatif->id)->where('jenis', 'formatif')->exists())->toBeTrue();
    expect(NilaiSiswa::where('asesmen_id', $asesmenFormatif->id)->first()->nilai_angka)->toBe(40);

    $narasi = app(CapaianKompetensiGenerator::class)->generateNarasi($siswa, $mapel, $semester);

    // Rata-rata KALAU tercampur: (90+40)/2 = 65, di bawah KKTP 75 -> akan hasilkan narasi "perlu bimbingan".
    // Rata-rata BENAR (Sumatif saja): 90, di atas KKTP 75 -> narasi "penguasaan sangat baik".
    expect($narasi['tertinggi'])->toContain('penguasaan sangat baik');
    expect($narasi['terendah'])->toBeNull();
});
