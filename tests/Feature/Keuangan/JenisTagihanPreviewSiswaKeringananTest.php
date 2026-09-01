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

it('lists siswa matching a draft sasaran with their current keringanan assignments', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'lulus']); // tidak match kriteria
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = SiswaKeringanan::create([
        'siswa_id' => $siswaAktif->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-siswa-keringanan'), [
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertOk();
    $response->assertJsonCount(1, 'siswa');
    $response->assertJsonPath('siswa.0.id', $siswaAktif->id);
    $response->assertJsonPath('siswa.0.assignments.'.$kategori->id, $assignment->id);
});

it('excludes an expired keringanan assignment from the assignments map', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);
    SiswaKeringanan::create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subMonths(2)->toDateString(),
        'berlaku_sampai' => now()->subMonth()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-siswa-keringanan'), [
        'sasaran' => [],
    ]);

    $response->assertOk();
    $response->assertJsonPath('siswa.0.assignments', []);
});
