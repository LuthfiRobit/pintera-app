<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create an asesmen and relate to guru, kelas, mapel, semester, and komponen penilaian', function () {
    $asesmen = Asesmen::factory()->create([
        'jenis' => JenisAsesmen::SumatifLingkupMateri,
        'judul' => 'Ulangan Harian Bab 1',
    ]);

    expect($asesmen->guru)->toBeInstanceOf(Guru::class);
    expect($asesmen->kelas)->toBeInstanceOf(Kelas::class);
    expect($asesmen->mataPelajaran)->toBeInstanceOf(MataPelajaran::class);
    expect($asesmen->semester)->toBeInstanceOf(Semester::class);
    expect($asesmen->jenis)->toBe(JenisAsesmen::SumatifLingkupMateri);

    $komponen = KomponenPenilaian::factory()->create([
        'mata_pelajaran_id' => $asesmen->mata_pelajaran_id,
        'semester_id' => $asesmen->semester_id,
    ]);

    $asesmen->komponenPenilaian()->attach($komponen);
    expect($asesmen->komponenPenilaian)->toHaveCount(1);
    expect($asesmen->komponenPenilaian->first()->id)->toBe($komponen->id);
});
