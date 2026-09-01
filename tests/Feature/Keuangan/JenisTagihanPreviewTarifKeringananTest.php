<?php

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('previews how many siswa match each tarif grup and how many siswa have each kategori keringanan', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $kategori = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim', 'bisa_digabung' => false]);

    $siswa1 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    $siswa2 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    SiswaKeringanan::create([
        'siswa_id' => $siswa1->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-tarif-keringanan'), [
        'tarif' => [
            ['nominal' => 200000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]],
        ],
        'keringanan' => [
            ['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'nominal', 'nilai_potongan' => 50000],
        ],
    ]);

    $response->assertOk();
    $response->assertJson([
        'tarif_counts' => [2],
        'keringanan_counts' => [$kategori->id => 1],
    ]);
});
