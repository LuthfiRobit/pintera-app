<?php

// tests/Feature/Guru/KomponenPenilaianAssessmentTypeUiTest.php

use App\Models\Semester;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('shows the Tipe Penilaian select on the guru komponen penilaian create form', function () {
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->assignRole('guru');
    \App\Models\Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $semester->lembaga_id]);

    $response = $this->actingAs($user)->get(route('guru.komponen-penilaian.create'));

    $response->assertOk();
    $response->assertSee('Tipe Penilaian');
    $response->assertSee('Predikat Capaian', false);
});
