<?php

namespace Tests\Feature\Akademik;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Guru;

it('does not collide rekap between a MataPelajaran and an ElemenCp sharing the same numeric id', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create();
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);

    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $elemen = ElemenCp::factory()->create();

    $asesmenMapel = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'guru_id' => $guru->id,
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id,
    ]);
    $asesmenElemen = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'guru_id' => $guru->id,
        'subjek_type' => 'elemen_cp', 'subjek_id' => $elemen->id,
    ]);

    $komponenMapel = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponenElemen = KomponenPenilaian::factory()->create(['subjek_type' => 'elemen_cp', 'subjek_id' => $elemen->id, 'semester_id' => $semester->id]);

    NilaiSiswa::create(['asesmen_id' => $asesmenMapel->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenMapel->id, 'nilai_angka' => 80]);
    NilaiSiswa::create(['asesmen_id' => $asesmenElemen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenElemen->id, 'nilai_angka' => 95]);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    expect($rekap['mapelList'])->toHaveCount(2);
    expect($rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id])->toBe(80.0);
    expect($rekap['rekapNilai'][$siswa->id]['elemen_cp:'.$elemen->id])->toBe(95.0);
});
