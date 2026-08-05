<?php
// tests/Feature/KasusShowDeleteButtonTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function actingAsKasusHapusAdminViewer(Lembaga $lembaga): User
{
    foreach (['kasus.view', 'kasus.triase', 'kasus.hapus'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.triase', 'kasus.hapus']);

    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    return $admin;
}

function actingAsKonselorPemegangKasusUntukShow(Kasus $kasus, Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kasus.view');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Konselor',
        'nik' => fake()->unique()->numerify('################'),
        'nip' => '9988776655', 'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_bk',
    ]);
    $kasus->update(['konselor_guru_id' => $guru->id]);

    return $user;
}

it('shows the Hapus Kasus delete form to an admin_akademik with kasus.hapus viewing a Selesai kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Selesai]);
    $admin = actingAsKasusHapusAdminViewer($lembaga);

    $response = $this->actingAs($admin)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee(route('admin.kasus.destroy', $kasus), false);
});

it('does not show the Hapus Kasus delete form to a konselor viewing a Selesai kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Selesai]);
    $konselor = actingAsKonselorPemegangKasusUntukShow($kasus, $lembaga);

    $response = $this->actingAs($konselor)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertDontSee(route('admin.kasus.destroy', $kasus), false);
});

it('does not show the Hapus Kasus delete form to an admin viewing a non-Selesai kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Berjalan]);
    $admin = actingAsKasusHapusAdminViewer($lembaga);

    $response = $this->actingAs($admin)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertDontSee(route('admin.kasus.destroy', $kasus), false);
});
