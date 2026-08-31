<?php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsOrangTuaManagerForQueryTest(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'orang-tua.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['orang-tua.view']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('searches orang tua by person nama_lengkap and exact NIK hash', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsOrangTuaManagerForQueryTest($lembaga);

    $u1 = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $u2 = User::factory()->create(['yayasan_id' => $yayasan->id]);

    $ortu1 = OrangTua::factory()->create(['user_id' => $u1->id, 'nama_lengkap' => 'Bapak Suryanto', 'nik' => '3201112233440001']);
    $ortu2 = OrangTua::factory()->create(['user_id' => $u2->id, 'nama_lengkap' => 'Ibu Hendrawati', 'nik' => '3201112233440002']);

    // Search by name partial
    $response = $this->actingAs($manager)->get(route('admin.orang-tua.index', ['search' => 'Suryanto']));
    $response->assertOk();
    $response->assertSee('Bapak Suryanto');
    $response->assertDontSee('Ibu Hendrawati');

    // Search by exact NIK (matched via sha256 nik_hash)
    $response2 = $this->actingAs($manager)->get(route('admin.orang-tua.index', ['search' => '3201112233440002']));
    $response2->assertOk();
    $response2->assertSee('Ibu Hendrawati');
    $response2->assertDontSee('Bapak Suryanto');
});

it('orders orang tua by person nama_lengkap correctly', function () {
    $yayasan = Yayasan::factory()->create();

    $o1 = OrangTua::factory()->create(['nama_lengkap' => 'Zainuddin']);
    $o2 = OrangTua::factory()->create(['nama_lengkap' => 'Baharuddin']);
    $o3 = OrangTua::factory()->create(['nama_lengkap' => 'Fachruddin']);

    $ordered = OrangTua::orderByNama()->get();
    expect($ordered->pluck('nama_lengkap')->toArray())->toBe(['Baharuddin', 'Fachruddin', 'Zainuddin']);
});
