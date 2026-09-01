<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('rejects lembaga as a sasaran kriteria field', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'lembaga', 'operator' => 'in', 'value' => [(string) $lembaga->id]]]]],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('sasaran.0.kriteria.0.field');
});

it('rejects a kelas kriteria value referencing a kelas from a different lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();
    $kelasLembagaLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelasLembagaLain->id]]]]],
    ]);

    $response->assertStatus(422);
});

it('accepts a kelas kriteria value referencing a kelas from the same lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelas->id]]]]],
    ]);

    $response->assertStatus(201);
});

it('still accepts non-kelas kriteria fields without triggering the kelas existence check', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertStatus(201);
});
