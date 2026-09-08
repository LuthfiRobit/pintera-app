<?php

use App\Domains\Akademik\Models\MataPelajaran;
use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsMataPelajaranManager(Lembaga $lembaga): User
{
    foreach (['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without mata-pelajaran.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.mata-pelajaran.index'))->assertForbidden();
});

it('creates a mata pelajaran with full standardized educational fields', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    $this->actingAs($manager)->post(route('admin.mata-pelajaran.store'), [
        'kode' => 'MTK-01',
        'nama' => 'Matematika',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    $mapel = MataPelajaran::where('kode', 'MTK-01')->first();
    expect($mapel)->not->toBeNull();
    expect($mapel->nama)->toBe('Matematika');
    expect($mapel->kelompok)->toBe(KelompokMataPelajaran::Umum);
});

it('only lists mata pelajaran belonging to the acting manager\'s own lembaga in index view', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembagaA);

    MataPelajaran::create([
        'lembaga_id' => $lembagaA->id,
        'kode' => 'A-01',
        'nama' => 'Mapel Lembaga A',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);
    MataPelajaran::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaB->id,
        'kode' => 'B-01',
        'nama' => 'Mapel Lembaga B',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertSee('Mapel Lembaga A');
    $response->assertSee('A-01');
    $response->assertDontSee('Mapel Lembaga B');
});

it('updates a mata pelajaran including status and no_urut', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);
    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'IPA-01',
        'nama' => 'IPA',
        'no_urut' => 2,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $this->actingAs($manager)->put(route('admin.mata-pelajaran.update', $mapel), [
        'kode' => 'IPA-01-REV',
        'nama' => 'Ilmu Pengetahuan Alam',
        'no_urut' => 5,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Pilihan->value,
        'status' => StatusMataPelajaran::Nonaktif->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    $fresh = $mapel->fresh();
    expect($fresh->kode)->toBe('IPA-01-REV');
    expect($fresh->nama)->toBe('Ilmu Pengetahuan Alam');
    expect($fresh->no_urut)->toBe(5);
    expect($fresh->kelompok)->toBe(KelompokMataPelajaran::Pilihan);
    expect($fresh->status)->toBe(StatusMataPelajaran::Nonaktif);
});

it('calculates executive KPI statistics accurately in index view', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'SD-01',
        'nama' => 'Matematika SD',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);
    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));
    $response->assertOk();
    $response->assertViewHas('totalMapel', 1);
    $response->assertViewHas('countKurikulum', 1);
});

it('returns only table partial view when requested via AJAX XMLHttpRequest', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'AJAX-01',
        'nama' => 'Mapel AJAX',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index', ['search' => 'AJAX']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();
    $response->assertViewIs('portals.lembaga.akademik.mata-pelajaran._daftar');
    $response->assertSee('Mapel AJAX');
});

it('shows the PAUD note banner with a link to Komponen Penilaian for KB/TPA/SPS/TK', function (string $bentukPendidikan) {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $manager = actingAsMataPelajaranManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertOk();
    $response->assertSee('Catatan untuk PAUD');
    $response->assertSee('Elemen CP');
    $response->assertSee(e(route('admin.komponen-penilaian.index')), false);
})->with(['KB', 'TPA', 'SPS', 'TK']);

it('does not show the PAUD note banner for a non-PAUD bentuk_pendidikan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $manager = actingAsMataPelajaranManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertOk();
    $response->assertDontSee('Catatan untuk PAUD');
});

it('rejects storing a mata pelajaran when the yayasan-scoped actor\'s active_lembaga_id session is stale (belongs to a different yayasan)', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_mapel_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['mata-pelajaran.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.mata-pelajaran.store'), [
        'kode' => 'STALE-01',
        'nama' => 'Mapel Uji Stale',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(MataPelajaran::withoutGlobalScopes()->where('kode', 'STALE-01')->exists())->toBeFalse();
});

it('redirects back with an error when a yayasan-scoped actor opens create without an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.create'))
        ->assertRedirect(route('admin.mata-pelajaran.index'))
        ->assertSessionHasErrors('lembaga_id');
});

it('shows the create form when a yayasan-scoped actor has switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.create'))->assertOk();
});

it('shows the PAUD note banner for a yayasan-scoped actor switched into a PAUD lembaga', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'))
        ->assertSee('Catatan untuk PAUD');
});

it('does not show the PAUD note banner for a yayasan-scoped actor in "Semua Lembaga" mode', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'))
        ->assertDontSee('Catatan untuk PAUD');
});

it('passes isYayasan and activeLembaga to both the full index page and the ajax partial', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    $this->actingAs($manager);

    $this->get(route('admin.mata-pelajaran.index'))->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);

    $this->get(route('admin.mata-pelajaran.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);
});

it('passes isYayasan and activeLembaga to the edit view', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'EDT-01',
        'nama' => 'Mapel Edit Uji',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // TIDAK switch lembaga -- mode "Semua Lembaga".

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.edit', $mapel))->assertOk()
        ->assertViewHas('isYayasan', true);
});
