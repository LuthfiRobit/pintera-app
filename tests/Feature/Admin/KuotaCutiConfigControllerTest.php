<?php
// tests/Feature/Admin/KuotaCutiConfigControllerTest.php

use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsAdminSdmKuotaCuti(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.kelola-konfigurasi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return $user;
}

it('renders the Kuota Cuti tab on the konfigurasi page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = actingAsAdminSdmKuotaCuti($lembaga);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.konfigurasi.index'));

    $response->assertOk()->assertSee('Kuota Cuti')->assertSee('kuota-cuti', false);
});

it('lets admin_sdm create a flat kuota cuti config for their lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = actingAsAdminSdmKuotaCuti($lembaga);

    $this->actingAs($user)->post(route('admin.kehadiran-sdm.kuota-cuti.store'), [
        'jatah_hari_per_tahun' => 12,
    ])->assertRedirect();

    expect(KuotaCutiConfig::where('lembaga_id', $lembaga->id)->where('jatah_hari_per_tahun', 12)->exists())->toBeTrue();
});

it('rejects creating a duplicate flat kuota cuti config for the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = actingAsAdminSdmKuotaCuti($lembaga);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 10]);

    $this->actingAs($user)->post(route('admin.kehadiran-sdm.kuota-cuti.store'), [
        'jatah_hari_per_tahun' => 15,
    ])->assertSessionHasErrors('jatah_hari_per_tahun');
});
