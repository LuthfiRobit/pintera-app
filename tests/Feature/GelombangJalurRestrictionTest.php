<?php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function buatGelombangDenganDuaJalur(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalurReguler = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $jalurPrestasi = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    return [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang];
}

it('lets a gelombang be attached to specific jalur via the pivot', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    $gelombang->jalur()->attach($jalurReguler->id);

    expect($gelombang->jalur()->pluck('jalur_ppdb.id')->all())->toBe([$jalurReguler->id]);
    expect($jalurReguler->gelombang()->pluck('gelombang_ppdb.id')->all())->toBe([$gelombang->id]);
    expect($jalurPrestasi->gelombang()->count())->toBe(0);
});

it('has zero pivot rows for a gelombang by default (unrestricted)', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    expect($gelombang->jalur()->exists())->toBeFalse();
});

it('shows every active jalur to the public when the gelombang is unrestricted', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertSee('Prestasi');
});

it('shows only the assigned jalur to the public when the gelombang is restricted', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});

it('never shows an inactive jalur to the public even if explicitly assigned to the gelombang', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $jalurPrestasi->update(['status_aktif' => false]);
    $gelombang->jalur()->attach([$jalurReguler->id, $jalurPrestasi->id]);

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});

it('rejects a jalur_id that belongs to a different tahun ajaran', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunLain = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $jalurTahunLain = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => 'Reguler Lama']);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 2',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 30,
        'jalur_ids' => [$jalurTahunLain->id],
    ])->assertSessionHasErrors('jalur_ids.0');

    expect(GelombangPpdb::where('nama', 'Gelombang 2')->exists())->toBeFalse();
});

it('syncs jalur_ids to the pivot on create', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombangLama] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 2',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 30,
        'jalur_ids' => [$jalurReguler->id],
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombangBaru = GelombangPpdb::where('nama', 'Gelombang 2')->firstOrFail();
    expect($gelombangBaru->jalur()->pluck('jalur_ppdb.id')->all())->toBe([$jalurReguler->id]);
});

it('rejects creating a gelombang when jalur_ids is omitted and active jalur exist', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombangLama] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 2',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 30,
        // jalur_ids omitted entirely
    ])->assertSessionHasErrors('jalur_ids');

    expect(GelombangPpdb::where('nama', 'Gelombang 2')->exists())->toBeFalse();
});

it('rejects updating a gelombang when jalur_ids is omitted, leaving its existing pivot untouched', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->put(route('admin.gelombang-ppdb.update', $gelombang), [
        'nama' => $gelombang->nama,
        'tanggal_buka' => $gelombang->tanggal_buka->format('Y-m-d'),
        'tanggal_tutup' => $gelombang->tanggal_tutup->format('Y-m-d'),
        'kuota' => $gelombang->kuota,
        // jalur_ids omitted entirely
    ])->assertSessionHasErrors('jalur_ids');

    expect($gelombang->jalur()->pluck('jalur_ppdb.id')->all())->toBe([$jalurReguler->id]);
});

it('rejects saving when every jalur checkbox is unchecked', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombangLama] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 2',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 30,
        'jalur_ids' => [],
    ])->assertSessionHasErrors('jalur_ids');

    expect(GelombangPpdb::where('nama', 'Gelombang 2')->exists())->toBeFalse();
});

it('shows a checkbox per active jalur on the create form, all pre-checked by default', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.gelombang-ppdb.create'));

    $response->assertOk()
        ->assertSee('Jalur yang Digunakan')
        ->assertSee('Reguler')
        ->assertSee('Prestasi');

    preg_match_all('/<input type="checkbox" name="jalur_ids\[\]" value="(\d+)"[^>]*>/', $response->getContent(), $matches, PREG_SET_ORDER);
    $checkedIds = collect($matches)->filter(fn ($m) => str_contains($m[0], 'checked'))->map(fn ($m) => (int) $m[1])->sort()->values()->all();

    expect($checkedIds)->toBe(collect([$jalurReguler->id, $jalurPrestasi->id])->sort()->values()->all());
});

it('pre-checks all active jalur on the edit form when the gelombang has no explicit restriction yet', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    // No attach() call here — this gelombang still has zero pivot rows,
    // the legacy/never-restricted state that predates this form.
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.gelombang-ppdb.edit', $gelombang));

    $response->assertOk();
    preg_match_all('/<input type="checkbox" name="jalur_ids\[\]" value="(\d+)"[^>]*>/', $response->getContent(), $matches, PREG_SET_ORDER);
    $checkedIds = collect($matches)->filter(fn ($m) => str_contains($m[0], 'checked'))->map(fn ($m) => (int) $m[1])->sort()->values()->all();

    expect($checkedIds)->toBe(collect([$jalurReguler->id, $jalurPrestasi->id])->sort()->values()->all());
});

it('pre-checks only the jalur already assigned on the edit form', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.gelombang-ppdb.edit', $gelombang));

    $response->assertOk();
    // The Reguler checkbox carries `checked`, Prestasi's does not — assert by
    // finding each input's own tag rather than a page-wide checked count,
    // since both inputs share surrounding markup.
    preg_match_all('/<input type="checkbox" name="jalur_ids\[\]" value="(\d+)"[^>]*>/', $response->getContent(), $matches, PREG_SET_ORDER);
    $checkedIds = collect($matches)->filter(fn ($m) => str_contains($m[0], 'checked'))->map(fn ($m) => (int) $m[1])->values()->all();

    expect($checkedIds)->toBe([$jalurReguler->id]);
});

it('shows a "N Jalur Aktif" badge for a gelombang with no explicit restriction yet (legacy zero pivot rows)', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('2 Jalur Aktif')
        ->assertDontSee('Jalur Dibatasi');
});

it('shows a "N Jalur Aktif" badge (not Dibatasi) when every active jalur is checked', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    // Simulates saving the form with every checkbox left checked: both of
    // the tahun ajaran's active jalur end up in the pivot, not zero rows.
    $gelombang->jalur()->attach([$jalurReguler->id, $jalurPrestasi->id]);
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('2 Jalur Aktif')
        ->assertDontSee('Jalur Dibatasi');
});

it('shows a "N Jalur Dibatasi" badge for a gelombang using fewer than all active jalur', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('1 Jalur Dibatasi');
});

it('seeds a restricted demo gelombang for the SD institution', function () {
    $this->seed();

    $sd = \App\Models\Lembaga::where('npsn', '20223333')->firstOrFail();
    $sdAktif = \App\Models\TahunAjaran::where('lembaga_id', $sd->id)->where('status_aktif', true)->firstOrFail();
    $gelombang1 = GelombangPpdb::where('lembaga_id', $sd->id)
        ->where('tahun_ajaran_id', $sdAktif->id)
        ->where('nama', 'Gelombang 1')
        ->firstOrFail();

    expect($gelombang1->jalur()->pluck('jalur_ppdb.nama')->sort()->values()->all())->toBe(['Prestasi', 'Reguler']);
});
