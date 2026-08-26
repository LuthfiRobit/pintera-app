<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('adds nullable subjek_type/subjek_id columns to komponen_penilaian and asesmen', function () {
    expect(Schema::hasColumns('komponen_penilaian', ['subjek_type', 'subjek_id']))->toBeTrue();
    expect(Schema::hasColumns('asesmen', ['subjek_type', 'subjek_id']))->toBeTrue();

    // Baris lama (mata_pelajaran_id NOT NULL) masih harus bisa di-insert
    // tanpa subjek_type/subjek_id -- membuktikan kolom baru benar nullable
    // dan tidak breaking di titik ini.
    $mapel = App\Domains\Akademik\Models\MataPelajaran::factory()->create();
    $semester = App\Models\Semester::factory()->create();

    $komponen = App\Domains\Akademik\Models\KomponenPenilaian::create([
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes',
        'bobot' => 10,
    ]);

    expect($komponen->subjek_type)->toBeNull();
    expect($komponen->subjek_id)->toBeNull();
});
