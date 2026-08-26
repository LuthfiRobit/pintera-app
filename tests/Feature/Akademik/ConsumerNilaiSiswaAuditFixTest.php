<?php

// tests/Feature/Akademik/ConsumerNilaiSiswaAuditFixTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates dashboard stats progress considering non-numeric assessment types and elemen_cp subjek', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $elemen = ElemenCp::factory()->create(['kode' => 'nilai_agama_moral']);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric',
    ]);
    $komponenPredicate = KomponenPenilaian::factory()->create([
        'subjek_type' => 'elemen_cp', 'subjek_id' => $elemen->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'predicate',
    ]);

    $asesmenMapel = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmenMapel->komponenPenilaian()->attach($komponenNumeric->id);

    $asesmenElemen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id, 'semester_id' => $semester->id,
    ]);
    $asesmenElemen->komponenPenilaian()->attach($komponenPredicate->id);

    // Input nilai predikat pada komponen predicate (nilai_angka NULL)
    NilaiSiswa::create([
        'asesmen_id' => $asesmenElemen->id,
        'siswa_id' => $siswa->id,
        'komponen_penilaian_id' => $komponenPredicate->id,
        'lembaga_id' => $lembaga,
        'nilai_angka' => null,
        'predikat' => 'BSH',
        'catatan' => 'Berkembang Sesuai Harapan',
    ]);

    $stats = app(DashboardStatsService::class)->statistikProgressRaporKelas($kelas);

    expect($stats['total'])->toBe(2); // 1 siswa x 2 komponen
    expect($stats['terisi'])->toBe(1); // 1 cell predikat terisi
    expect($stats['persen'])->toBe(50.0);
});
