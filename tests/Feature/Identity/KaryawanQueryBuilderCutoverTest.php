<?php

use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKaryawanManagerForQueryTest(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'karyawan.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['karyawan.view']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('searches karyawan by person nama_lengkap', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKaryawanManagerForQueryTest($lembaga);

    $k1 = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'nama' => 'Budi Satpam']);
    $k2 = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'nama' => 'Siti Admin']);

    $response = $this->actingAs($manager)->get(route('admin.karyawan.index', ['search' => 'Satpam']));
    $response->assertOk();
    $response->assertSee('Budi Satpam');
    $response->assertDontSee('Siti Admin');
});

it('orders karyawan by person nama_lengkap correctly', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $k1 = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'nama' => 'Zulfa']);
    $k2 = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'nama' => 'Adam']);
    $k3 = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'nama' => 'Maya']);

    $ordered = Karyawan::where('lembaga_id', $lembaga->id)->orderByNama()->get();
    expect($ordered->pluck('nama')->toArray())->toBe(['Adam', 'Maya', 'Zulfa']);
});
