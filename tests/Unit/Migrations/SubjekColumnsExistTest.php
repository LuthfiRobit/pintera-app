<?php

use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('verifies subjek_type/subjek_id columns exist and legacy columns are dropped on komponen_penilaian and asesmen', function () {
    expect(Schema::hasColumns('komponen_penilaian', ['subjek_type', 'subjek_id']))->toBeTrue();
    expect(Schema::hasColumns('asesmen', ['subjek_type', 'subjek_id']))->toBeTrue();

    // Verify legacy columns have been dropped in Task 6
    expect(Schema::hasColumn('komponen_penilaian', 'mata_pelajaran_id'))->toBeFalse();
    expect(Schema::hasColumn('komponen_penilaian', 'elemen_cp'))->toBeFalse();
    expect(Schema::hasColumn('asesmen', 'mata_pelajaran_id'))->toBeFalse();

    // Verify polymorphic subjek creation works
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create();

    $komponen = KomponenPenilaian::create([
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes Polymorphic Subjek',
        'bobot' => 10,
    ]);

    expect($komponen->subjek_type)->toBe('mata_pelajaran');
    expect($komponen->subjek_id)->toBe($mapel->id);
});
