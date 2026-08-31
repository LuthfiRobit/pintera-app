<?php

// tests/Feature/Keuangan/ChildSwitcherTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows the child switcher dropdown when the orang tua has more than one child', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Pertama']);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Kedua']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dua Anak',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);
    $orangTua->siswa()->attach($siswaA->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->siswa()->attach($siswaB->id, ['hubungan' => 'ayah', 'is_kontak_utama' => false]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Anak Pertama');
    $response->assertSee('Anak Kedua');
    $response->assertSee(route('keuangan.dashboard', ['switch_siswa' => $siswaB->id]), false);
});

it('does not show the child switcher for a single-child orang tua', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Tunggal']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Satu Anak',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Pilih Profil Anak');
});

it('shows the kontak utama child in both header and switcher label when kontak utama is not alphabetically first', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    // "Aan" sorts alphabetically before "Zainal", but "Zainal" is kontak utama —
    // the switcher label and dashboard header must both reflect Zainal, not the
    // alphabetically-first Aan.
    $siswaAlfabetisPertama = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Aan Firstname']);
    $siswaKontakUtama = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Zainal Kontak Utama']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Kontak Utama Tidak Alfabetis',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $orangTua->siswa()->attach($siswaAlfabetisPertama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => false]);
    $orangTua->siswa()->attach($siswaKontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    // Dashboard header (middleware-resolved activeSiswa) shows kontak utama.
    $response->assertSee('lunasi tagihan aktif Zainal Kontak Utama', false);
    // Switcher chip label (topbar, driven by resolvedActiveSiswaId) also shows kontak utama.
    $response->assertSeeInOrder(['Pilih Profil Anak', 'Zainal Kontak Utama']);
});

it('does not show the child switcher for non-orang_tua roles', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $user = User::factory()->create();
    $user->assignRole('guru');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Pilih Profil Anak');
});
