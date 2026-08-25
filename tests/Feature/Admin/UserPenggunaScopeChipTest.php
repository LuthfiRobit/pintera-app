<?php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('seedSemuaRoleUntukChipTest')) {
    function seedSemuaRoleUntukChipTest(): void
    {
        $roles = [
            'platform_super_admin' => 'platform',
            'yayasan_super_admin' => 'yayasan',
            'bendahara_yayasan' => 'yayasan',
            'pegawai_yayasan' => 'yayasan',
            'kepala_sekolah' => 'lembaga',
            'admin_administrasi' => 'lembaga',
            'pegawai_lembaga' => 'lembaga',
            'guru' => 'diri_sendiri',
            'wali_kelas' => 'lembaga',
            'orang_tua' => 'diri_sendiri',
            'siswa' => 'diri_sendiri',
        ];

        foreach ($roles as $name => $scopeLevel) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['scope_level' => $scopeLevel, 'is_protected' => in_array($name, ['platform_super_admin', 'yayasan_super_admin'], true)]);
        }
    }
}

if (! function_exists('buatPlatformAdminUntukChipTest')) {
    function buatPlatformAdminUntukChipTest(): User
    {
        foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        seedSemuaRoleUntukChipTest();
        $role = Role::where('name', 'platform_super_admin')->firstOrFail();
        $role->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

        $admin = User::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }
}

it('shows only pegawai_lembaga-family accounts and their count when the lembaga chip is active, excluding staf who also carry the pegawai_lembaga baseline role', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'kepsek.chip@example.test']);
    $kepsek->assignRole(['kepala_sekolah', 'pegawai_lembaga']);

    // Guru sungguhan SELALU juga punya pegawai_lembaga (baseline invariant RBAC v2) -- ini
    // skenario yang tadinya bikin guru bocor ke chip Lembaga sebelum applyScopeGroup()
    // dibuat precedence-aware. Test ini SENGAJA memakai kombinasi role itu.
    $guru = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'guru.chip@example.test']);
    $guru->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['scope_group' => 'lembaga']));

    $response->assertOk();
    $response->assertSee('kepsek.chip@example.test');
    $response->assertDontSee('guru.chip@example.test');
});

it('shows only staf-family accounts when the staf chip is active, excluding pegawai_lembaga-only admins', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'kepsek.staf@example.test']);
    $kepsek->assignRole(['kepala_sekolah', 'pegawai_lembaga']);

    $guru = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'guru.staf@example.test']);
    $guru->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['scope_group' => 'staf']));

    $response->assertOk();
    $response->assertSee('guru.staf@example.test');
    $response->assertDontSee('kepsek.staf@example.test');
});

it('shows only platform_super_admin accounts when the platform chip is active', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $yayasanAdmin = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id, 'email' => 'yayasan.platformchip@example.test']);
    $yayasanAdmin->assignRole('yayasan_super_admin');

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['scope_group' => 'platform']));

    $response->assertOk();
    $response->assertSee($admin->email);
    $response->assertDontSee('yayasan.platformchip@example.test');
});

it('displays correct scope chip counts matching the actual filtered results', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $guru1 = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru1->assignRole(['guru', 'pegawai_lembaga']);
    $guru2 = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru2->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertViewHas('scopeCounts', function ($scopeCounts) {
        return $scopeCounts['staf'] === 2;
    });
});

it('lets a platform_super_admin see users across multiple yayasan on the Pengguna page, with Yayasan column visible', function () {
    $admin = buatPlatformAdminUntukChipTest();

    $yayasanA = Yayasan::factory()->create(['nama' => 'Yayasan Alpha']);
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id, 'email' => 'usera.lintas@example.test']);
    $userA->assignRole(['guru', 'pegawai_lembaga']);

    $yayasanB = Yayasan::factory()->create(['nama' => 'Yayasan Beta']);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $userB = User::factory()->create(['lembaga_id' => $lembagaB->id, 'email' => 'userb.lintas@example.test']);
    $userB->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('usera.lintas@example.test');
    $response->assertSee('userb.lintas@example.test');
    $response->assertSee('Yayasan Alpha');
    $response->assertSee('Yayasan Beta');
});

it('does not show the Yayasan column for a non-platform viewer', function () {
    foreach (['users.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    seedSemuaRoleUntukChipTest();
    $role = Role::where('name', 'yayasan_super_admin')->firstOrFail();
    $role->givePermissionTo('users.view');

    $manager = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertDontSee('<th class="px-5 py-3">Yayasan</th>', false);
});

it('lets a lembaga-scoped manager see an orang_tua account whose child studies in their own lembaga', function () {
    foreach (['users.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    seedSemuaRoleUntukChipTest();
    $role = Role::where('name', 'admin_administrasi')->firstOrFail();
    $role->givePermissionTo('users.view');

    $lembaga = Lembaga::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null, 'name' => 'Ortu Sama Lembaga']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('Ortu Sama Lembaga');
});

it('does not let a lembaga-scoped manager see an orang_tua account whose child studies in a different lembaga', function () {
    foreach (['users.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    seedSemuaRoleUntukChipTest();
    $role = Role::where('name', 'admin_administrasi')->firstOrFail();
    $role->givePermissionTo('users.view');

    $lembagaSaya = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $manager->assignRole($role);

    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null, 'name' => 'Ortu Lembaga Lain']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswaLain->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertDontSee('Ortu Lembaga Lain');
});

it('shows only orang_tua accounts when the orang_tua scope chip is active, resolved via their child siswa lembaga', function () {
    foreach (['users.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    seedSemuaRoleUntukChipTest();
    $role = Role::where('name', 'admin_administrasi')->firstOrFail();
    $role->givePermissionTo('users.view');

    $lembaga = Lembaga::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null, 'name' => 'Ortu Chip Filter']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $guru = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'guru.bukanortu@example.test']);
    $guru->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($manager)->get(route('admin.users.index', ['scope_group' => 'orang_tua']));

    $response->assertOk();
    $response->assertSee('Ortu Chip Filter');
    $response->assertDontSee('guru.bukanortu@example.test');
});

it('lets a platform_super_admin see orang_tua accounts regardless of which lembaga their child belongs to', function () {
    $admin = buatPlatformAdminUntukChipTest();

    $lembagaA = Lembaga::factory()->create();
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $orangTuaUserA = User::factory()->create(['lembaga_id' => null, 'name' => 'Ortu Platform A']);
    $orangTuaUserA->assignRole('orang_tua');
    $orangTuaA = OrangTua::factory()->create(['user_id' => $orangTuaUserA->id]);
    $orangTuaA->siswa()->attach($siswaA->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('Ortu Platform A');
});
