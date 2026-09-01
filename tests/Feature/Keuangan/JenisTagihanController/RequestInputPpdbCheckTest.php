<?php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function actingUserForJenisTagihan(): User
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    return $user;
}

it('treats a valid PPDB kategori string from raw request input as PPDB', function () {
    $user = actingUserForJenisTagihan();

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Pendaftaran Siswa Baru',
        'kategori' => 'pendaftaran',
    ]);

    $response->assertStatus(201);
    $data = $response->json();
    expect($data['redirect'] ?? null)->toContain('nominal');
});

it('treats an invalid/missing kategori string from raw request input as NOT PPDB, without erroring', function () {
    $user = actingUserForJenisTagihan();

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Jenis Tagihan Tidak Valid',
        'kategori' => 'not-a-real-value',
    ]);

    // The invalid kategori value fails normal field validation (Rule::in in baseRules),
    // but this must surface as a clean 422 validation error — never a 500/ValueError from
    // KategoriTagihan::tryFrom() choking on an unrecognised string.
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['kategori']);
});
