<?php

// tests/Unit/Models/KomponenPenilaianAssessmentTypeTest.php

use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Enums\PredikatPaud;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults assessment_type to numeric at the database level when not specified', function () {
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $mapel->lembaga_id]);

    $komponen = KomponenPenilaian::create([
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'deskripsi' => 'Tes default DB',
        'bobot' => 10,
    ]);

    expect($komponen->fresh()->assessment_type)->toBe(AssessmentType::Numeric);
});

it('casts predikat on NilaiSiswa to the PredikatPaud enum', function () {
    $nilai = NilaiSiswa::factory()->create(['predikat' => 'BSH', 'nilai_angka' => null]);

    expect($nilai->fresh()->predikat)->toBe(PredikatPaud::BSH);
});
