<?php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function buatAdminLembagaDenganPermission(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'pengaturan-akademik.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_akademik_test', 'guard_name' => 'web']);
    $role->givePermissionTo('pengaturan-akademik.kelola');

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return $user;
}

it('admin dgn permission bisa update batas edit absen dgn nilai valid', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'batas_edit_absen_hari' => 3]);
    $admin = buatAdminLembagaDenganPermission($lembaga);

    $response = $this->actingAs($admin)->putJson(route('admin.pengaturan.akademik.batas-edit-absen'), ['batas_edit_absen_hari' => 10]);

    $response->assertOk();
    $response->assertJson(['data' => ['batas_edit_absen_hari' => 10]]);
    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(10);
});

it('menolak nilai di luar rentang 1-365', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = buatAdminLembagaDenganPermission($lembaga);

    $response = $this->actingAs($admin)->putJson(route('admin.pengaturan.akademik.batas-edit-absen'), ['batas_edit_absen_hari' => 0]);

    $response->assertStatus(422);
});

it('menolak user tanpa permission pengaturan-akademik.kelola', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->putJson(route('admin.pengaturan.akademik.batas-edit-absen'), ['batas_edit_absen_hari' => 10]);

    $response->assertStatus(403);
});
