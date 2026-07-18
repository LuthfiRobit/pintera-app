<?php

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminPpdbDenganTahunAktif(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}

it('shows an empty-state prompt when the lembaga has no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('Aktifkan tahun ajaran');
});

it('creates a gelombang scoped to the active tahun ajaran', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 40,
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombang = GelombangPpdb::first();
    expect($gelombang->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($gelombang->lembaga_id)->toBe($lembaga->id);
});

it('rejects a gelombang whose tanggal_tutup is before tanggal_buka', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-09-01',
        'tanggal_tutup' => '2026-08-01',
        'kuota' => 40,
    ])->assertSessionHasErrors('tanggal_tutup');

    expect(GelombangPpdb::count())->toBe(0);
});

it('404s when a lembaga-scoped admin opens the edit page for a gelombang in another lembaga', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherGelombang = GelombangPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id,
        'nama' => 'Gelombang 1', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.edit', $otherGelombang))->assertNotFound();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->get(route('admin.gelombang-ppdb.index'))->assertForbidden();
});

it('redirects a yayasan-scoped user with no active lembaga selected away from the create page', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasanRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasanRole->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    $yayasanUser = User::factory()->create(['lembaga_id' => null]);
    $yayasanUser->assignRole($yayasanRole);

    $this->actingAs($yayasanUser)->get(route('admin.gelombang-ppdb.create'))
        ->assertRedirect(route('admin.gelombang-ppdb.index'))
        ->assertSessionHasErrors('lembaga_id');
});

it('rejects a store from a yayasan-scoped user with no active lembaga selected, without creating a row', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasanRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasanRole->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    $yayasanUser = User::factory()->create(['lembaga_id' => null]);
    $yayasanUser->assignRole($yayasanRole);

    $this->actingAs($yayasanUser)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 40,
    ])->assertSessionHasErrors('lembaga_id');

    expect(GelombangPpdb::count())->toBe(0);
});

it('rejects a duplicate gelombang nama within the same tahun ajaran instead of crashing', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang 1', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 40,
    ])->assertSessionHasErrors('nama');

    expect(GelombangPpdb::count())->toBe(1);
});

it('lets an update keep its own unchanged nama without a false-positive uniqueness error', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang 1', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->put(route('admin.gelombang-ppdb.update', $gelombang), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 50,
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    expect($gelombang->fresh()->kuota)->toBe(50);
});

it('does not show the "Salin dari" callout when the only prior tahun ajaran has no gelombang or jalur data', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertDontSee('Salin dari');
});

it('filters the index by nama when cari is given', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang Prestasi', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang Reguler', 'tanggal_buka' => '2026-09-02', 'tanggal_tutup' => '2026-10-01', 'kuota' => 60,
    ]);

    $response = $this->actingAs($user)->get(route('admin.gelombang-ppdb.index', ['cari' => 'Prestasi']));

    expect($response->viewData('gelombangList')->pluck('nama')->all())->toBe(['Gelombang Prestasi']);
});

it('lets the tahun_ajaran filter browse a past year instead of only the active one', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $tahunLalu = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Gelombang Tahun Lalu', 'tanggal_buka' => '2025-08-01', 'tanggal_tutup' => '2025-09-01', 'kuota' => 30,
    ]);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang Tahun Ini', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);

    $default = $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'));
    expect($default->viewData('gelombangList')->pluck('nama')->all())->toBe(['Gelombang Tahun Ini']);

    $pastYear = $this->actingAs($user)->get(route('admin.gelombang-ppdb.index', ['tahun_ajaran' => $tahunLalu->id]));
    expect($pastYear->viewData('gelombangList')->pluck('nama')->all())->toBe(['Gelombang Tahun Lalu']);
});

it('paginates the index at 10 per page', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    foreach (range(1, 12) as $i) {
        $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        GelombangPpdb::create([
            'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => "Gelombang {$i}", 'tanggal_buka' => "2026-08-{$day}", 'tanggal_tutup' => "2026-09-{$day}", 'kuota' => 10,
        ]);
    }

    $response = $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'));

    $response->assertOk();
    expect($response->viewData('gelombangList'))->toHaveCount(10);
    expect($response->viewData('gelombangList')->total())->toBe(12);
});

it('shows the owning lembaga name under each gelombang name', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang 1', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('Gelombang 1')
        ->assertSee($lembaga->nama);
});

it('lets a yayasan-scoped user with an active lembaga selected via the switcher create a gelombang scoped to it', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasanRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasanRole->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    $yayasanUser = User::factory()->create(['lembaga_id' => null]);
    $yayasanUser->assignRole($yayasanRole);

    // Switch to the target lembaga via the ResolveTenant middleware query param.
    $this->actingAs($yayasanUser)->get('/dashboard?switch_lembaga='.$lembaga->id);

    $this->actingAs($yayasanUser)->get(route('admin.gelombang-ppdb.create'))->assertOk();

    $this->actingAs($yayasanUser)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 40,
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombang = GelombangPpdb::first();
    expect($gelombang->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($gelombang->lembaga_id)->toBe($lembaga->id);
});
