<?php

use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('previews how many siswa match a draft sasaran without saving anything', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'lulus']);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-sasaran'), [
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertOk();
    $response->assertJson(['count' => 3]);
});
