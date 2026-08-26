<?php

namespace Tests\Feature\Akademik;

use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('rejects a komponen penilaian whose mata_pelajaran belongs to a different lembaga than the semester', function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();

    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembagaB->id]);

    $user = User::factory()->create(['lembaga_id' => $lembagaB->id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $response = $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes',
        'bobot' => 10,
    ]);

    $response->assertNotFound();
});

it('accepts a komponen penilaian with subjek_type=elemen_cp regardless of the acting lembaga (global reference data)', function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();

    $lembaga = Lembaga::factory()->create();
    $elemen = ElemenCp::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $response = $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes PAUD',
        'bobot' => 10,
    ]);

    $response->assertRedirect();
    expect(KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->exists())->toBeTrue();
});
