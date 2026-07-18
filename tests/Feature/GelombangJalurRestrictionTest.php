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

it('creates an unrestricted gelombang when jalur_ids is omitted entirely', function () {
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
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombangBaru = GelombangPpdb::where('nama', 'Gelombang 2')->firstOrFail();
    expect($gelombangBaru->jalur()->exists())->toBeFalse();
});

it('clears the pivot back to unrestricted when an update omits jalur_ids after it was previously restricted', function () {
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
        // jalur_ids omitted entirely, simulating every checkbox left unchecked
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    expect($gelombang->jalur()->exists())->toBeFalse();
});

it('shows a checkbox per active jalur on the create form, none pre-checked', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.create'))
        ->assertOk()
        ->assertSee('Batasi Jalur')
        ->assertSee('Reguler')
        ->assertSee('Prestasi')
        ->assertSee('value="'.$jalurReguler->id.'"', false)
        ->assertDontSee('checked', false);
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
