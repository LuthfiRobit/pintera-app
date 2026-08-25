<?php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsUserManagerForFormRedesignTest(): User
{
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

    $user = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $user->assignRole($role);

    return $user;
}

it('links a siswa row to the Data Siswa edit route instead of admin.users.edit', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaUser = User::factory()->create(['username' => 'siswa.linktest', 'email' => null, 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $siswaUser->id]);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee(route('admin.siswa.edit', $siswa), false);
    $response->assertDontSee(route('admin.users.edit', $siswaUser), false);
});

it('links an orang_tua row to the Orang Tua module edit route instead of admin.users.edit', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null, 'name' => 'Ortu Link Test']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee(route('admin.orang-tua.edit', $orangTua), false);
    $response->assertDontSee(route('admin.users.edit', $orangTuaUser), false);
});

it('does not show a toggle-active action for siswa or orang_tua rows', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaUser = User::factory()->create(['username' => 'siswa.notoggle', 'email' => null, 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertDontSee(route('admin.users.toggle-active', $siswaUser), false);
});

it('renders Lembaga Tertaut on the profile tab based on lembaga_id, not the old dead-code role string comparison', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'nama' => 'SD Uji Coba']);

    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole('kepala_sekolah');

    $response = $this->actingAs($manager)->get(route('admin.users.edit', $kepsek));

    $response->assertOk();
    $response->assertSee('Lembaga Tertaut');
    $response->assertSee('SD Uji Coba');
});

it('formats role names to Title Case on the users index page list and filter select options', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('wakasek_kurikulum');

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    // 1. Check in the dropdown select filter: option label should be Title Case
    $response->assertSee('<option value="wakasek_kurikulum" >Wakasek Kurikulum</option>', false);
    // 2. Check in the table list row
    $response->assertSee('Wakasek Kurikulum');
});

it('formats role names to Title Case on the user edit and profile view tabs', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('wakasek_kurikulum');

    $response = $this->actingAs($manager)->get(route('admin.users.edit', $user));

    $response->assertOk();
    // Check in the hero role list
    $response->assertSee('Wakasek Kurikulum');
    // Check in the Profile tab access info dd list
    $response->assertSee('Wakasek Kurikulum');
});
