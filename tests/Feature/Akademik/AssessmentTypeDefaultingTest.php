<?php

// tests/Feature/Akademik/AssessmentTypeDefaultingTest.php

use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('defaults to narrative when subjek_type=elemen_cp and assessment_type is not sent', function () {
    $elemen = ElemenCp::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes default narrative',
        'bobot' => 10,
    ])->assertRedirect();

    $komponen = KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->firstOrFail();
    expect($komponen->assessment_type)->toBe(AssessmentType::Narrative);
});

it('defaults to numeric when subjek_type=mata_pelajaran and assessment_type is not sent', function () {
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $mapel->lembaga_id]);
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes default numeric',
        'bobot' => 10,
    ])->assertRedirect();

    $komponen = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mapel->id)->firstOrFail();
    expect($komponen->assessment_type)->toBe(AssessmentType::Numeric);
});

it('honors an explicit assessment_type override even when it contradicts the subjek_type default', function () {
    $elemen = ElemenCp::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes override',
        'bobot' => 10,
        'assessment_type' => 'predicate',
    ])->assertRedirect();

    $komponen = KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->firstOrFail();
    expect($komponen->assessment_type)->toBe(AssessmentType::Predicate);
});

it('rejects an invalid assessment_type value at the request boundary', function () {
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $mapel->lembaga_id]);
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $response = $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes invalid',
        'bobot' => 10,
        'assessment_type' => 'foobar',
    ]);

    $response->assertSessionHasErrors('assessment_type');
    expect(KomponenPenilaian::where('deskripsi', 'Tes invalid')->exists())->toBeFalse();
});
