<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('lists only tagihan with perlu_ditinjau_ulang=true, scoped to the acting lembaga, with a selesai-ditinjau button', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $flagged = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'Alasan uji']);
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'perlu_ditinjau_ulang' => false]);

    $response = $this->actingAs($admin)->get(route('admin.tagihan.perlu-ditinjau'));

    $response->assertOk();
    $response->assertSee('Alasan uji');
    $response->assertSee(route('admin.tagihan.selesai-ditinjau', $flagged), false);
});
