<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function siapkanUserPersonal(string $roleName): User
{
    $permissions = [
        'guru' => ['presensi.isi', 'asesmen.kelola', 'kehadiran-sdm.lihat-qr-sendiri'],
        'orang_tua' => ['keuangan.akses', 'kasus.view'],
        'siswa' => ['kasus.view'],
    ];

    foreach ($permissions[$roleName] ?? [] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    if (! empty($permissions[$roleName])) {
        $role->givePermissionTo($permissions[$roleName]);
    }

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($roleName);

    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);

    if ($roleName === 'guru') {
        Guru::factory()->create(['user_id' => $user->id, 'person_id' => $person->id, 'lembaga_id' => $lembaga->id]);
    } elseif ($roleName === 'orang_tua') {
        OrangTua::factory()->create(['user_id' => $user->id, 'person_id' => $person->id]);
    } elseif ($roleName === 'siswa') {
        Siswa::factory()->create(['user_id' => $user->id, 'person_id' => $person->id, 'lembaga_id' => $lembaga->id]);
    }

    return $user;
}

it('renders Guru bottom nav with 5 slots including QR Saya FAB for guru account', function () {
    $guru = siapkanUserPersonal('guru');

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('aria-label="Beranda"', false);
    $response->assertSee('aria-label="Jurnal"', false);
    $response->assertSee('aria-label="QR Saya"', false);
    $response->assertSee('aria-label="Nilai"', false);
    $response->assertSee('aria-label="Buka menu"', false);
    $response->assertSee(route('guru.jurnal-kbm.index'));
    $response->assertSee(route('sdm.qr-saya'));
    $response->assertSee(route('guru.asesmen.index'));
    $response->assertSee('sidebarOpen = true', false);
});

it('renders Orang Tua bottom nav with 5 flat slots for orang tua account', function () {
    $ortu = siapkanUserPersonal('orang_tua');

    $response = $this->actingAs($ortu)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('aria-label="Beranda"', false);
    $response->assertSee('aria-label="Nilai Anak"', false);
    $response->assertSee('aria-label="Tagihan"', false);
    $response->assertSee('aria-label="Presensi Anak"', false);
    $response->assertSee('aria-label="Buka menu"', false);
    $response->assertSee(route('keuangan.dashboard'));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'nilai-anak']));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'riwayat-izin-sakit-anak']));
});

it('renders Siswa bottom nav with 5 flat slots for siswa account', function () {
    $siswa = siapkanUserPersonal('siswa');

    $response = $this->actingAs($siswa)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('aria-label="Beranda"', false);
    $response->assertSee('aria-label="Jadwal Pelajaran"', false);
    $response->assertSee('aria-label="Presensi Saya"', false);
    $response->assertSee('aria-label="Nilai &amp; Rapor"', false);
    $response->assertSee('aria-label="Buka menu"', false);
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'jadwal-pelajaran']));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'presensi-saya']));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'nilai-rapor']));
});

it('does not render bottom nav for non-personal accounts (admin, staff, yayasan)', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $admin->assignRole($adminRole);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('id="bottom-nav"', false);
    $response->assertDontSee('aria-label="Buka menu"', false);
});

it('correctly matches active state for placeholder routes based on fitur query parameter', function () {
    $siswa = siapkanUserPersonal('siswa');

    $response = $this->actingAs($siswa)->get(route('dalam-pengembangan', ['fitur' => 'jadwal-pelajaran']));

    $response->assertOk();
    $response->assertSee('data-active="jadwal-pelajaran"', false);
    $response->assertDontSee('data-active="presensi-saya"', false);
    $response->assertDontSee('data-active="nilai-rapor"', false);
});

it('hides the Nilai slot for a guru account whose role lacks asesmen.kelola, without breaking the 5-slot grid', function () {
    $guru = siapkanUserPersonal('guru');
    Role::where('name', 'guru')->first()->revokePermissionTo('asesmen.kelola');
    $guru->unsetRelation('permissions')->unsetRelation('roles');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('aria-label="Beranda"', false);
    $response->assertSee('aria-label="Jurnal"', false);
    $response->assertSee('aria-label="QR Saya"', false);
    $response->assertDontSee('aria-label="Nilai"', false);
});

it('shows Pintera brand logo in topbar on mobile for personal accounts and burger button for non-personal accounts', function () {
    $guru = siapkanUserPersonal('guru');

    $responseGuru = $this->actingAs($guru)->get(route('dashboard'));
    $responseGuru->assertOk();
    $responseGuru->assertSee('lg:hidden', false);
    $responseGuru->assertSee(config('app.name', 'Pintera'));

    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $admin->assignRole($adminRole);

    $responseAdmin = $this->actingAs($admin)->get(route('dashboard'));
    $responseAdmin->assertOk();
    $responseAdmin->assertSee('aria-label="Buka/tutup sidebar"', false);
});
