<?php

namespace Tests\Feature\Guru;

use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('renders create page and stores a komponen penilaian with subjek_type=elemen_cp for guru', function () {
    (new PermissionSeeder)->run();
    (new RoleSeeder)->run();

    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'TK']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');
    $user->givePermissionTo('komponen-penilaian.kelola-sendiri');
    $guru->person->update(['user_id' => $user->id]);

    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $elemen = ElemenCp::factory()->create();

    $getResponse = $this->actingAs($user)->get(route('guru.komponen-penilaian.create'));
    $getResponse->assertOk();
    $getResponse->assertSee('subjek_type');

    $postResponse = $this->actingAs($user)->post(route('guru.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Anak mengenal ciptaan Tuhan',
        'bobot' => 100,
    ]);

    $postResponse->assertRedirect(route('guru.komponen-penilaian.index'));

    expect(KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->exists())->toBeTrue();
});
