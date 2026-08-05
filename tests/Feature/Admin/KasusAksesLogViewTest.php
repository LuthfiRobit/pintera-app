<?php
// tests/Feature/Admin/KasusAksesLogViewTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function actingAsKasusLogViewer(Lembaga $lembaga): User
{
    foreach (['kasus.view', 'kasus.lihat-log-akses'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.lihat-log-akses']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

function bukaHalamanKasusSebagaiKonselor(Kasus $kasus, Lembaga $lembaga): User
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
    test()->actingAs($user)->get(route('kasus.show', $kasus))->assertOk();

    return $user;
}

it('lists akses_klinis log rows scoped to admin_akademik own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaSendiri = Siswa::factory()->create(['lembaga_id' => $lembagaSendiri->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kasusSendiri = Kasus::factory()->create(['siswa_id' => $siswaSendiri->id, 'lembaga_id' => $lembagaSendiri->id, 'status' => StatusKasus::Berjalan]);
    $kasusLain = Kasus::factory()->create(['siswa_id' => $siswaLain->id, 'lembaga_id' => $lembagaLain->id, 'status' => StatusKasus::Berjalan]);
    bukaHalamanKasusSebagaiKonselor($kasusSendiri, $lembagaSendiri);
    bukaHalamanKasusSebagaiKonselor($kasusLain, $lembagaLain);

    $viewer = actingAsKasusLogViewer($lembagaSendiri);

    $response = $this->actingAs($viewer)->get(route('admin.kasus.log-akses'));

    $response->assertOk();
    $response->assertSee($siswaSendiri->nama_lengkap);
    $response->assertDontSee($siswaLain->nama_lengkap);
});

it('lets yayasan_super_admin see akses_klinis log rows across all lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kasusA = Kasus::factory()->create(['siswa_id' => $siswaA->id, 'lembaga_id' => $lembagaA->id, 'status' => StatusKasus::Berjalan]);
    $kasusB = Kasus::factory()->create(['siswa_id' => $siswaB->id, 'lembaga_id' => $lembagaB->id, 'status' => StatusKasus::Berjalan]);
    bukaHalamanKasusSebagaiKonselor($kasusA, $lembagaA);
    bukaHalamanKasusSebagaiKonselor($kasusB, $lembagaB);

    Permission::firstOrCreate(['name' => 'kasus.lihat-log-akses', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('kasus.lihat-log-akses');
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $response = $this->actingAs($superAdmin)->get(route('admin.kasus.log-akses'));

    $response->assertOk();
    $response->assertSee($siswaA->nama_lengkap);
    $response->assertSee($siswaB->nama_lengkap);
});

it('403s a user without kasus.lihat-log-akses permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kasus.view');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.kasus.log-akses'))->assertForbidden();
});
