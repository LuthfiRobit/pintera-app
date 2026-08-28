<?php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruUntukSidebar(): User
{
    foreach (['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali', 'rpp.view', 'rpp.kelola', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.lihat-sendiri', 'kasus.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali', 'rpp.view', 'rpp.kelola', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.lihat-sendiri', 'kasus.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');
    Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id]);

    return $user;
}

it('shows RPP, QR Kehadiran, Izin/Cuti, and Kasus Pendampingan under Ruang Guru for a guru account', function () {
    $guru = siapkanGuruUntukSidebar();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Ruang Guru', 'Perangkat Ajar (RPP)']);
    $response->assertSee('QR Kehadiran Saya');
    $response->assertSee('Izin/Cuti Saya');
});

it('shows Ruang Siswa group with 3 dalam-pengembangan links for a siswa account', function () {
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);

    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Ruang Siswa');
    $response->assertSee('Nilai &amp; Rapor', false);
    $response->assertSee('Jadwal Pelajaran');
    $response->assertSee('Presensi Saya');
});

