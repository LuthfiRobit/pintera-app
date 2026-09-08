<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKelasManager(Lembaga $lembaga): User
{
    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without kelas.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kelas.index'))->assertForbidden();
});

it('creates a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $manager = actingAsKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
    ])->assertRedirect(route('admin.kelas.index'));

    expect(Kelas::where('nama', '6A')->exists())->toBeTrue();
});

it('offers only guru belonging to the current lembaga as wali kelas options', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKelasManager($lembagaA);

    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567891111',
        'nama' => 'Guru Lembaga B',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kelas.create'));

    $response->assertViewHas('guruList', function ($guruList) use ($guruA) {
        return $guruList->count() === 1 && $guruList->first()->id === $guruA->id;
    });
});

it('rejects creating a kelas with a tahun_ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => 'Kelas Campur Lembaga',
        'tingkat' => '6',
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Campur Lembaga')->exists())->toBeFalse();
});

it('rejects creating a kelas with a wali_kelas_guru_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $guruLain = Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaLain->id])->id,
        'lembaga_id' => $lembagaLain->id,
        'nik' => '3201234567892222',
        'nama' => 'Guru Lain Lembaga',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas Wali Campur',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guruLain->id,
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Wali Campur')->exists())->toBeFalse();
});

it('rejects creating a kelas with a pola_jam_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas Pola Campur',
        'tingkat' => '6',
        'pola_jam_id' => $polaLain->id,
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Pola Campur')->exists())->toBeFalse();
});

it('rejects updating a kelas to a tahun_ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);
    $kelas = Kelas::create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaranSaya->id, 'nama' => '6A']);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => '6A',
        'tingkat' => '6',
    ])->assertNotFound();

    expect($kelas->fresh()->tahun_ajaran_id)->toBe($tahunAjaranSaya->id);
});

it('rejects updating a kelas with a wali_kelas_guru_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $manager = actingAsKelasManager($lembagaSaya);
    $kelas = Kelas::create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $guruLain = Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaLain->id])->id,
        'lembaga_id' => $lembagaLain->id,
        'nik' => '3201234567893333',
        'nama' => 'Guru Lain Lembaga Dua',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guruLain->id,
    ])->assertNotFound();

    expect($kelas->fresh()->wali_kelas_guru_id)->toBeNull();
});

it('rejects updating a kelas with a pola_jam_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $manager = actingAsKelasManager($lembagaSaya);
    $kelas = Kelas::create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
        'pola_jam_id' => $polaLain->id,
    ])->assertNotFound();

    expect($kelas->fresh()->pola_jam_id)->toBeNull();
});

it('updates a kelas including assigning a wali kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKelasManager($lembaga);
    $kelas = Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guru->id,
    ])->assertRedirect(route('admin.kelas.index'));

    expect($kelas->fresh()->wali_kelas_guru_id)->toBe($guru->id);
});

it('menolak actor yayasan dengan active_lembaga_id stale (lembaga di luar yayasannya) saat membuat kelas', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_kelas_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['kelas.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'nama' => 'Kelas Uji Stale',
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'tingkat' => '1',
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(Kelas::where('nama', 'Kelas Uji Stale')->exists())->toBeFalse();
});

it('redirects back with an error when a yayasan-scoped actor opens create without an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.kelas.create'))
        ->assertRedirect(route('admin.kelas.index'))
        ->assertSessionHasErrors('lembaga_id');
});

it('shows the create form when a yayasan-scoped actor has switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kelas.create'))->assertOk();
});

it('only offers tahun ajaran belonging to the active lembaga in the create dropdown for a yayasan-scoped actor', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027 A']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => '2026/2027 B']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaA->id]);

    $response = $this->actingAs($manager)->get(route('admin.kelas.create'))->assertOk();
    $response->assertViewHas('tahunAjaranList', function ($list) use ($taA) {
        return $list->count() === 1 && $list->first()->id === $taA->id;
    });
});

it('only offers tahun ajaran and guru belonging to the kelas lembaga in the edit dropdown, even in "Semua Lembaga" mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027 A']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => '2026/2027 B']);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567894444',
        'nama' => 'Guru Lembaga B Edit',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);
    $kelas = Kelas::create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $taA->id, 'nama' => '6A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // TIDAK switch ke lembaga manapun -- mode "Semua Lembaga" aktif.

    $response = $this->actingAs($manager)->get(route('admin.kelas.edit', $kelas))->assertOk();
    $response->assertViewHas('tahunAjaranList', fn ($list) => $list->count() === 1 && $list->first()->id === $taA->id);
    $response->assertViewHas('guruList', fn ($list) => $list->count() === 1 && $list->first()->id === $guruA->id);
});

it('passes isYayasan and activeLembaga to both the full index page and the ajax partial', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    $this->actingAs($manager);

    $this->get(route('admin.kelas.index'))->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);

    $this->get(route('admin.kelas.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);
});

it('shows the "Semua Lembaga" badge on the kelas index for a yayasan-scoped actor in aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.kelas.index'))->assertSee('Semua Lembaga');
});

it('shows the switched lembaga name badge on the create page (never the "Semua Lembaga" variant)', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cakrawala Ilmu']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kelas.create'))
        ->assertSee('SD Cakrawala Ilmu')
        ->assertDontSee('border-purple-200');
});

it('shows the owning lembaga name badge on the edit page even in "Semua Lembaga" mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cakrawala Ilmu']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '7A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // TIDAK switch lembaga -- mode "Semua Lembaga".

    $this->actingAs($manager)->get(route('admin.kelas.edit', $kelas))->assertSee('SMP Cakrawala Ilmu');
});

it('shows a Lembaga column with each lembaga name in the kelas table during aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Melati Satu']);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Melati Dua']);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    Kelas::create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $taA->id, 'nama' => '1A']);
    Kelas::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'nama' => '1A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.kelas.index'))->assertOk();
    $response->assertSee('SD Melati Satu');
    $response->assertSee('SD Melati Dua');
});

it('hides the Lembaga column once a lembaga is switched into', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '1A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kelas.index'))->assertDontSee('>Lembaga<', false);
});

it('shows the lembaga name suffix in the Tahun Ajaran filter dropdown during aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Pelita Bangsa']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.kelas.index'))
        ->assertSee('2026/2027 — SMA Pelita Bangsa', false);
});

it('renders the wali kelas field with searchable tomSelectPegawai integration', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nip' => '198001012010011001']);
    $manager = actingAsKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kelas.create'));

    $response->assertOk();
    $response->assertSee('tomSelectPegawai', false);
    $response->assertSee('NIP: 198001012010011001', false);
});

it('pre-selects existing wali kelas in the edit form with tomSelectPegawai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'wali_kelas_guru_id' => $guru->id,
    ]);
    $manager = actingAsKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kelas.edit', $kelas));

    $response->assertOk();
    $response->assertSee('tomSelectPegawai', false);
    $response->assertSee('oldValue: '.$guru->id, false);
});
